<?php

namespace Suzuran;

use RuntimeException;

class WavConvertValue
{
    /** @var int[] */
    private array $samples = [];
    private int $sampleRate = 0;

    public function __construct(string $file)
    {
        $stream = fopen($file, 'rb');
        if ($stream === false) {
            throw new RuntimeException("cannot open file: {$file}");
        }

        try {
            if (fread($stream, 4) !== 'RIFF') {
                throw new RuntimeException('not a RIFF file');
            }
            fread($stream, 4);
            if (fread($stream, 4) !== 'WAVE') {
                throw new RuntimeException('not a WAVE file');
            }

            $dataFound = false;
            while (!feof($stream)) {
                $id = fread($stream, 4);
                if ($id === '' || $id === false || strlen($id) < 4) {
                    break;
                }
                $sizeBytes = fread($stream, 4);
                if ($sizeBytes === false || strlen($sizeBytes) < 4) {
                    break;
                }
                $size = unpack('V', $sizeBytes)[1];

                if ($id === 'fmt ') {
                    $this->readFmt($stream, $size);
                } elseif ($id === 'data') {
                    $this->readData($stream, $size);
                    $dataFound = true;
                    break;
                } else {
                    fseek($stream, $size, SEEK_CUR);
                }
                // RIFF chunks are word-aligned; skip pad byte on odd sizes.
                if ($size % 2 === 1) {
                    fseek($stream, 1, SEEK_CUR);
                }
            }

            if (!$dataFound) {
                throw new RuntimeException('data chunk not found');
            }
        } finally {
            fclose($stream);
        }
    }

    /** @return int[] */
    public function samples(): array
    {
        return $this->samples;
    }

    public function sampleRate(): int
    {
        return $this->sampleRate;
    }

    /** @param resource $stream */
    private function readFmt($stream, int $size): void
    {
        $body = fread($stream, $size);
        if ($body === false || strlen($body) < 16) {
            throw new RuntimeException('malformed fmt chunk');
        }
        $fmt = unpack(
            'vformatCode/vchannels/VsampleRate/VbyteRate/vblockAlign/vbitsPerSample',
            substr($body, 0, 16)
        );
        if ($fmt['formatCode'] !== 1) {
            throw new RuntimeException('only PCM (formatCode=1) is supported');
        }
        if ($fmt['channels'] !== 1) {
            throw new RuntimeException('only mono (channels=1) is supported');
        }
        if ($fmt['bitsPerSample'] !== 16) {
            throw new RuntimeException('only 16-bit PCM is supported');
        }
        $this->sampleRate = $fmt['sampleRate'];
    }

    /** @param resource $stream */
    private function readData($stream, int $size): void
    {
        $bytes = '';
        $remaining = $size;
        while ($remaining > 0) {
            $chunk = fread($stream, $remaining);
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('failed to read data chunk');
            }
            $bytes .= $chunk;
            $remaining -= strlen($chunk);
        }

        $unsigned = unpack('v*', $bytes);
        $signed = [];
        foreach ($unsigned as $u) {
            // unsigned uint16 LE -> signed int16
            $signed[] = $u >= 0x8000 ? $u - 0x10000 : $u;
        }
        $this->samples = $signed;
    }
}
