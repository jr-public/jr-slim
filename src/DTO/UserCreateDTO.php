<?php
namespace App\DTO;

use App\DTO\DataTransferObjectInterface;

use Symfony\Component\Validator\Constraints as Assert;

class UserCreateDTO implements DataTransferObjectInterface
{
    #[Assert\NotBlank(message: 'Username is required')]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: 'Username must be at least {{ limit }} characters long',
        maxMessage: 'Username cannot be longer than {{ limit }} characters'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_]+$/',
        message: 'Username can only contain letters, numbers, and underscores'
    )]
    public string $username;

    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Please provide a valid email address')]
    #[Assert\Length(max: 255, maxMessage: 'Email cannot be longer than {{ limit }} characters')]
    public string $email;

    #[Assert\NotBlank(message: 'Password is required')]
    #[Assert\Length(
        min: 4,
        max: 128,
        minMessage: 'Password must be at least {{ limit }} characters long',
        maxMessage: 'Password cannot be longer than {{ limit }} characters'
    )]
    // #[Assert\Regex(
    //     pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/',
    //     message: 'Password must contain at least one lowercase letter, one uppercase letter, and one number'
    // )]
    public string $password;

    public function toArray(): array {
        return get_object_vars($this);
    }
}