<?php
namespace App\Exception;

class NotFoundException extends ApiException {
    public function __construct(
        string $message = 'NOT_FOUND_ERROR',
        ?string $detail = null,
        int $httpStatus = 404
    ) {
        parent::__construct($message, $detail, $httpStatus);
    }
}