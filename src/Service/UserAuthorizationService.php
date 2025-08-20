<?php
namespace App\Service;

use App\Entity\User;

use App\Exception\AuthException;

class UserAuthorizationService
{
	public function applyAccessControl(?User $user): void
	{
        if (!$user) {
            throw new AuthException('BAD_USER');
        }
        if ($user->get('status') !== 'active') {
            throw new AuthException('NOT_ACTIVE');
        }
        if ($user->get('reset_password')) {
            throw new AuthException('RESET_PASSWORD');
        }
	}
    public function verifyPassword(string $hashed, string $password): bool
    {
        return password_verify($password, $hashed);
    }
}
