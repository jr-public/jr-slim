<?php
namespace App\DTO;

use App\DTO\DataTransferObjectInterface;
use Symfony\Component\Validator\Constraints as Assert;

class ResendActivationDTO implements DataTransferObjectInterface
{
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Please provide a valid email address')]
    #[Assert\Length(max: 255, maxMessage: 'Email cannot be longer than {{ limit }} characters')]
    public string $email;

    public function toArray(): array {
        return get_object_vars($this);
    }
}