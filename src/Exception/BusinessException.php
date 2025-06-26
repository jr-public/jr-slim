<?php
namespace App\Exception;

class BusinessException extends ApiException {
    public function __construct(
        string $message = 'BUSINESS_ERROR',
        ?string $detail = null,
        int $httpStatus = 422
    ) {
        parent::__construct($message, $detail, $httpStatus);
    }
}