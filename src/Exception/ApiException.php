<?php
namespace App\Exception;

class ApiException extends \Exception {
    protected ?string $detail;
    protected int $httpStatus;
    public function __construct(
        string $message = 'API_ERROR',
        ?string $detail = null,
        int $httpStatus = 500
    ) {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
        $this->detail = $detail;
    }

    public function getDetail(): ?string {
        return $this->detail;
    }
    public function getHttpStatus(): int {
        return $this->httpStatus;
    }
}
