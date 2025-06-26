<?php
namespace App\Exception;

class ValidationException extends ApiException {
    public function __construct(
        string $message = 'VALIDATION_ERROR',
        ?string $detail = null,
        int $httpStatus = 400
    ) {
        parent::__construct($message, $detail, $httpStatus);
    }
}