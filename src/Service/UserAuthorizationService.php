<?php
namespace App\Service;

use App\Entity\User;

use App\Exception\AuthException;

class UserAuthorizationService
{
	public function applyAccessControl(User $user): void
	{
        if ($user->get('status') !== 'active') {
            throw new AuthException('NOT_ACTIVE');
        }
        if ($user->get('reset_password')) {
            throw new AuthException('RESET_PASSWORD');
        }
	}
}
