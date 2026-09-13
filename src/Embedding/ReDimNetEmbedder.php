<?php

namespace Suzuran\Embedding;

use FFI;
use RuntimeException;

/**
 * Loads libonnxruntime via FFI and runs ReDimNet2.onnx to produce a 192-d speaker embedding.
 *
 * Verified against ONNX Runtime 1.17 (brew's `onnxruntime`). The OrtApi struct declaration
 * only names the function-pointer fields this class actually calls; every other slot is a
 * padding `void*` because C function pointers share sizeof on any supported platform.
 * If ONNX Runtime reshuffles OrtApi (rare, but possible on major version bumps), the fields
 * used here must be re-verified against onnxruntime_c_api.h.
 */
class ReDimNetEmbedder implements EmbedderInterface
{
    private const ORT_API_VERSION = 17;
    private const OUTPUT_DIM = 192;

    private FFI $ffi;
    /** @var \FFI\CData */
    private $api;
    /** @var \FFI\CData */
    private $env;
    /** @var \FFI\CData */
    private $session;
    /** @var \FFI\CData */
    private $sessionOptions;
    /** @var \FFI\CData */
    private $memoryInfo;
    private string $inputName;
    private string $outputName;

    public function __construct(
        string $modelPath,
        ?string $libraryPath = null,
    ) {
        if (!file_exists($modelPath)) {
            throw new RuntimeException("ONNX model not found: {$modelPath}");
        }
        $libraryPath ??= $this->defaultLibraryPath();
        if (!file_exists($libraryPath)) {
            throw new RuntimeException("libonnxruntime not found: {$libraryPath}");
        }

        $this->ffi = FFI::cdef($this->headerSource(), $libraryPath);

        $base = $this->ffi->OrtGetApiBase();
        // PHP FFI cannot call struct function-pointer fields via ->method();
        // it needs the extra parens: ($struct->fn)(args).
        $this->api = ($base[0]->GetApi)(self::ORT_API_VERSION);
        if (FFI::isNull($this->api)) {
            throw new RuntimeException('OrtGetApiBase()->GetApi returned null (API version mismatch)');
        }

        $envPtr = $this->ffi->new('OrtEnv*');
        $this->check(($this->api[0]->CreateEnv)(3 /* WARNING */, 'suzuran', FFI::addr($envPtr)));
        $this->env = $envPtr;

        $optsPtr = $this->ffi->new('OrtSessionOptions*');
        $this->check(($this->api[0]->CreateSessionOptions)(FFI::addr($optsPtr)));
        $this->sessionOptions = $optsPtr;

        $sessionPtr = $this->ffi->new('OrtSession*');
        $this->check(($this->api[0]->CreateSession)(
            $this->env,
            $modelPath,
            $this->sessionOptions,
            FFI::addr($sessionPtr),
        ));
        $this->session = $sessionPtr;

        $memPtr = $this->ffi->new('OrtMemoryInfo*');
        $this->check(($this->api[0]->CreateCpuMemoryInfo)(0 /* Arena */, 0 /* CPU */, FFI::addr($memPtr)));
        $this->memoryInfo = $memPtr;

        $allocatorPtr = $this->ffi->new('OrtAllocator*');
        $this->check(($this->api[0]->GetAllocatorWithDefaultOptions)(FFI::addr($allocatorPtr)));

        $this->inputName = $this->readName($this->session, $allocatorPtr, true);
        $this->outputName = $this->readName($this->session, $allocatorPtr, false);
    }

    public function __destruct()
    {
        if (isset($this->session)) {
            ($this->api[0]->ReleaseSession)($this->session);
        }
        if (isset($this->sessionOptions)) {
            ($this->api[0]->ReleaseSessionOptions)($this->sessionOptions);
        }
        if (isset($this->memoryInfo)) {
            ($this->api[0]->ReleaseMemoryInfo)($this->memoryInfo);
        }
        if (isset($this->env)) {
            ($this->api[0]->ReleaseEnv)($this->env);
        }
    }

    public function embed(array $waveform): array
    {
        $n = count($waveform);
        if ($n === 0) {
            throw new RuntimeException('empty waveform');
        }

        $buffer = $this->ffi->new("float[$n]", false);
        for ($i = 0; $i < $n; $i++) {
            $buffer[$i] = (float) $waveform[$i];
        }

        $shape = $this->ffi->new('int64_t[2]', false);
        $shape[0] = 1;
        $shape[1] = $n;

        $inputTensor = $this->ffi->new('OrtValue*');
        $this->check(($this->api[0]->CreateTensorWithDataAsOrtValue)(
            $this->memoryInfo,
            FFI::addr($buffer[0]),
            $n * FFI::sizeof($this->ffi->type('float')),
            $shape,
            2,
            1 /* ONNX_TENSOR_ELEMENT_DATA_TYPE_FLOAT */,
            FFI::addr($inputTensor),
        ));

        $inputNames = $this->ffi->new('char*[1]', false);
        $inputNameC = $this->ffi->new('char[' . (strlen($this->inputName) + 1) . ']', false);
        FFI::memcpy($inputNameC, $this->inputName, strlen($this->inputName));
        $inputNames[0] = $this->ffi->cast('char*', FFI::addr($inputNameC[0]));

        $outputNames = $this->ffi->new('char*[1]', false);
        $outputNameC = $this->ffi->new('char[' . (strlen($this->outputName) + 1) . ']', false);
        FFI::memcpy($outputNameC, $this->outputName, strlen($this->outputName));
        $outputNames[0] = $this->ffi->cast('char*', FFI::addr($outputNameC[0]));

        $inputs = $this->ffi->new('OrtValue*[1]', false);
        $inputs[0] = $inputTensor;
        $outputs = $this->ffi->new('OrtValue*[1]', false);
        $outputs[0] = null;

        $this->check(($this->api[0]->Run)(
            $this->session,
            null,
            $this->ffi->cast('const char* const*', FFI::addr($inputNames[0])),
            $this->ffi->cast('const OrtValue* const*', FFI::addr($inputs[0])),
            1,
            $this->ffi->cast('const char* const*', FFI::addr($outputNames[0])),
            1,
            FFI::addr($outputs[0]),
        ));

        try {
            $dataPtr = $this->ffi->new('void*');
            $this->check(($this->api[0]->GetTensorMutableData)($outputs[0], FFI::addr($dataPtr)));
            $floatPtr = $this->ffi->cast("float[" . self::OUTPUT_DIM . "]", $dataPtr);

            $embedding = [];
            for ($i = 0; $i < self::OUTPUT_DIM; $i++) {
                $embedding[] = (float) $floatPtr[$i];
            }
            return $embedding;
        } finally {
            ($this->api[0]->ReleaseValue)($outputs[0]);
            ($this->api[0]->ReleaseValue)($inputTensor);
            FFI::free($buffer);
            FFI::free($shape);
        }
    }

    /** @param \FFI\CData|null $status */
    private function check($status): void
    {
        // PHP FFI auto-converts NULL pointers into PHP null on function-pointer returns.
        if ($status === null || FFI::isNull($status)) {
            return;
        }
        $msg = ($this->api[0]->GetErrorMessage)($status);
        $msg = is_string($msg) ? $msg : FFI::string($msg);
        ($this->api[0]->ReleaseStatus)($status);
        throw new RuntimeException("OrtStatus: {$msg}");
    }

    /** @param \FFI\CData $session @param \FFI\CData $allocator */
    private function readName($session, $allocator, bool $isInput): string
    {
        $namePtr = $this->ffi->new('char*');
        $status = $isInput
            ? ($this->api[0]->SessionGetInputName)($session, 0, $allocator, FFI::addr($namePtr))
            : ($this->api[0]->SessionGetOutputName)($session, 0, $allocator, FFI::addr($namePtr));
        $this->check($status);
        $name = FFI::string($namePtr);
        ($this->api[0]->AllocatorFree)($allocator, $this->ffi->cast('void*', $namePtr));
        return $name;
    }

    private function defaultLibraryPath(): string
    {
        $candidates = [
            '/opt/homebrew/lib/libonnxruntime.dylib',
            '/usr/local/lib/libonnxruntime.dylib',
            '/usr/lib/x86_64-linux-gnu/libonnxruntime.so',
            '/usr/local/lib/libonnxruntime.so',
        ];
        foreach ($candidates as $c) {
            if (file_exists($c)) {
                return $c;
            }
        }
        return $candidates[0];
    }

    private function headerSource(): string
    {
        // Minimum OrtApi surface used by this class. Slot order must match the ORDER OF FIELDS in
        // OrtApi from onnxruntime_c_api.h (verified against ONNX Runtime 1.17). Unused slots are
        // declared as `void* padN` because sizeof(function pointer) == sizeof(void*) on all
        // supported platforms.
        return <<<'C'
        typedef struct OrtEnv OrtEnv;
        typedef struct OrtStatus OrtStatus;
        typedef struct OrtSession OrtSession;
        typedef struct OrtSessionOptions OrtSessionOptions;
        typedef struct OrtValue OrtValue;
        typedef struct OrtMemoryInfo OrtMemoryInfo;
        typedef struct OrtAllocator OrtAllocator;
        typedef struct OrtRunOptions OrtRunOptions;

        typedef struct OrtApi {
            void* pad0_CreateStatus;
            void* pad1_GetErrorCode;
            const char* (*GetErrorMessage)(OrtStatus*);
            OrtStatus* (*CreateEnv)(int log_level, const char* logid, OrtEnv**);
            void* pad4_CreateEnvWithCustomLogger;
            void* pad5_EnableTelemetryEvents;
            void* pad6_DisableTelemetryEvents;
            OrtStatus* (*CreateSession)(const OrtEnv*, const char* model_path, const OrtSessionOptions*, OrtSession**);
            void* pad8_CreateSessionFromArray;
            OrtStatus* (*Run)(OrtSession*, const OrtRunOptions*, const char* const* input_names, const OrtValue* const* inputs, size_t input_count, const char* const* output_names, size_t output_count, OrtValue** outputs);
            OrtStatus* (*CreateSessionOptions)(OrtSessionOptions**);
            void* pad11_SetOptimizedModelFilePath;
            void* pad12_CloneSessionOptions;
            void* pad13_SetSessionExecutionMode;
            void* pad14_EnableProfiling;
            void* pad15_DisableProfiling;
            void* pad16_EnableMemPattern;
            void* pad17_DisableMemPattern;
            void* pad18_EnableCpuMemArena;
            void* pad19_DisableCpuMemArena;
            void* pad20_SetSessionLogId;
            void* pad21_SetSessionLogVerbosityLevel;
            void* pad22_SetSessionLogSeverityLevel;
            void* pad23_SetSessionGraphOptimizationLevel;
            void* pad24_SetIntraOpNumThreads;
            void* pad25_SetInterOpNumThreads;
            void* pad26_CreateCustomOpDomain;
            void* pad27_CustomOpDomain_Add;
            void* pad28_AddCustomOpDomain;
            void* pad29_RegisterCustomOpsLibrary;
            OrtStatus* (*SessionGetInputCount)(const OrtSession*, size_t*);
            OrtStatus* (*SessionGetOutputCount)(const OrtSession*, size_t*);
            void* pad32_SessionGetOverridableInitializerCount;
            void* pad33_SessionGetInputTypeInfo;
            void* pad34_SessionGetOutputTypeInfo;
            void* pad35_SessionGetOverridableInitializerTypeInfo;
            OrtStatus* (*SessionGetInputName)(const OrtSession*, size_t, OrtAllocator*, char**);
            OrtStatus* (*SessionGetOutputName)(const OrtSession*, size_t, OrtAllocator*, char**);
            void* pad38_SessionGetOverridableInitializerName;
            void* pad39_CreateRunOptions;
            void* pad40_RunOptionsSetRunLogVerbosityLevel;
            void* pad41_RunOptionsSetRunLogSeverityLevel;
            void* pad42_RunOptionsSetRunTag;
            void* pad43_RunOptionsGetRunLogVerbosityLevel;
            void* pad44_RunOptionsGetRunLogSeverityLevel;
            void* pad45_RunOptionsGetRunTag;
            void* pad46_RunOptionsSetTerminate;
            void* pad47_RunOptionsUnsetTerminate;
            void* pad48_CreateTensorAsOrtValue;
            OrtStatus* (*CreateTensorWithDataAsOrtValue)(const OrtMemoryInfo*, void* p_data, size_t p_data_len, const int64_t* shape, size_t shape_len, int type, OrtValue**);
            void* pad50_IsTensor;
            OrtStatus* (*GetTensorMutableData)(OrtValue*, void**);
            void* pad52_FillStringTensor;
            void* pad53_GetStringTensorDataLength;
            void* pad54_GetStringTensorContent;
            void* pad55_CastTypeInfoToTensorInfo;
            void* pad56_GetOnnxTypeFromTypeInfo;
            void* pad57_CreateTensorTypeAndShapeInfo;
            void* pad58_SetTensorElementType;
            void* pad59_SetDimensions;
            void* pad60_GetTensorElementType;
            void* pad61_GetDimensionsCount;
            void* pad62_GetDimensions;
            void* pad63_GetSymbolicDimensions;
            void* pad64_GetTensorShapeElementCount;
            void* pad65_GetTensorTypeAndShape;
            void* pad66_GetTypeInfo;
            void* pad67_GetValueType;
            void* pad68_CreateMemoryInfo;
            OrtStatus* (*CreateCpuMemoryInfo)(int allocator_type, int mem_type, OrtMemoryInfo**);
            void* pad70_CompareMemoryInfo;
            void* pad71_MemoryInfoGetName;
            void* pad72_MemoryInfoGetId;
            void* pad73_MemoryInfoGetMemType;
            void* pad74_MemoryInfoGetType;
            void* pad75_AllocatorAlloc;
            void (*AllocatorFree)(OrtAllocator*, void*);
            void* pad77_AllocatorGetInfo;
            OrtStatus* (*GetAllocatorWithDefaultOptions)(OrtAllocator**);
            void* pad79_AddFreeDimensionOverride;
            void* pad80_GetValue;
            void* pad81_GetValueCount;
            void* pad82_CreateValue;
            void* pad83_CreateOpaqueValue;
            void* pad84_GetOpaqueValue;
            void* pad85_KernelInfoGetAttribute_float;
            void* pad86_KernelInfoGetAttribute_int64;
            void* pad87_KernelInfoGetAttribute_string;
            void* pad88_KernelContext_GetInputCount;
            void* pad89_KernelContext_GetOutputCount;
            void* pad90_KernelContext_GetInput;
            void* pad91_KernelContext_GetOutput;
            void (*ReleaseEnv)(OrtEnv*);
            void (*ReleaseStatus)(OrtStatus*);
            void (*ReleaseMemoryInfo)(OrtMemoryInfo*);
            void (*ReleaseSession)(OrtSession*);
            void (*ReleaseValue)(OrtValue*);
            void* pad97_ReleaseRunOptions;
            void* pad98_ReleaseTypeInfo;
            void* pad99_ReleaseTensorTypeAndShapeInfo;
            void (*ReleaseSessionOptions)(OrtSessionOptions*);
        } OrtApi;

        typedef struct OrtApiBase {
            const OrtApi* (*GetApi)(uint32_t version);
            const char* (*GetVersionString)(void);
        } OrtApiBase;

        const OrtApiBase* OrtGetApiBase(void);
        C;
    }
}
