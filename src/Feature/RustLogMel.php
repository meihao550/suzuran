<?php

namespace Suzuran\Feature;

use RuntimeException;

class RustLogMel implements LogMelInterface
{
    public function __construct(private string $binary = __DIR__ . '/../../bin/logmel-rs')
    {
    }

    public function compute(array $waveform): array
    {
        if (!is_executable($this->binary)) {
            throw new RuntimeException("logmel-rs binary not found or not executable: {$this->binary}");
        }

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($this->binary, $descriptors, $pipes);
        if (!is_resource($proc)) {
            throw new RuntimeException('failed to spawn logmel-rs');
        }

        try {
            $payload = '';
            foreach ($waveform as $f) {
                $payload .= pack('g', (float) $f);
            }
            fwrite($pipes[0], $payload);
            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]) ?: '';
            fclose($pipes[1]);
            $stderr = stream_get_contents($pipes[2]) ?: '';
            fclose($pipes[2]);

            $status = proc_close($proc);
            if ($status !== 0) {
                throw new RuntimeException("logmel-rs exit={$status}: {$stderr}");
            }
        } catch (\Throwable $e) {
            @proc_close($proc);
            throw $e;
        }

        if (strlen($stdout) < 8) {
            throw new RuntimeException('logmel-rs output too short');
        }
        $header = unpack('VnMels/VnFrames', substr($stdout, 0, 8));
        $nMels = $header['nMels'];
        $nFrames = $header['nFrames'];
        $expected = 8 + $nMels * $nFrames * 4;
        if (strlen($stdout) < $expected) {
            throw new RuntimeException('logmel-rs payload truncated');
        }

        $flat = [];
        if ($nMels * $nFrames > 0) {
            $flat = array_values(unpack('g*', substr($stdout, 8, $nMels * $nFrames * 4)));
        }

        $mel = array_fill(0, $nMels, array_fill(0, $nFrames, 0.0));
        for ($m = 0; $m < $nMels; $m++) {
            for ($t = 0; $t < $nFrames; $t++) {
                $mel[$m][$t] = $flat[$m * $nFrames + $t];
            }
        }
        return $mel;
    }
}
