<?php
namespace App\Exception;

class AuthException extends ApiException {
    public function __construct(
        string $message = 'AUTH_ERROR',
        ?string $detail = null,
        int $httpStatus = 401
    ) {
        parent::__construct($message, $detail, $httpStatus);
    }
}
