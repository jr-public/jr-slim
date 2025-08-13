<?php
namespace App\DTO;

use App\DTO\DataTransferObjectInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ResetPasswordDTO implements DataTransferObjectInterface
{
    #[Assert\Type('string')]
    #[Assert\NotBlank]
    public string $token;
    #[Assert\Type('string')]
    #[Assert\NotBlank]
    public string $password;

    public function toArray(): array {
        return get_object_vars($this);
    }
}