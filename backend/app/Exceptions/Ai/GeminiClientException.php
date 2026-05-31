<?php

namespace App\Exceptions\Ai;

use Exception;

class GeminiClientException extends Exception
{
    public const MISSING_API_KEY = 'missing_api_key';

    public const TIMEOUT = 'timeout';

    public const RATE_LIMIT = 'rate_limit';

    public const SERVER_ERROR = 'server_error';

    public const INVALID_RESPONSE = 'invalid_response';

    public const API_ERROR = 'api_error';

    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly ?int $httpStatus = null,
        public readonly ?int $latencyMs = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }
}
