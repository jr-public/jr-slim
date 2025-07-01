<?php
namespace App\DTO;

use App\DTO\DataTransferObjectInterface;
use App\Entity\User;

use Symfony\Component\Validator\Constraints as Assert;

class UserPatchDTO implements DataTransferObjectInterface
{
    
	#[Assert\Valid]
	public User $user;

    #[Assert\Type('string')]
    #[Assert\Choice(
        choices: ['password', 'email'],
        message: 'Invalid mutable property.'
    )]
    public string $property;

    #[Assert\NotBlank(message: 'Property value is required')]
    public string $value;

    public function toArray(): array {
        return get_object_vars($this);
    }
}