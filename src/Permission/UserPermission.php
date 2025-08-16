<?php
namespace App\Permission;

use App\Entity\User;

class UserPermission {
	// Role hierarchy defined as constants for clarity
    private const ROLE_USER 		= 'user';
    private const ROLE_MODERATOR 	= 'moderator';
    private const ROLE_ADMIN 		= 'admin';
    // Role hierarchy mapping (higher roles can manage lower roles)
    private const ROLE_HIERARCHY = [
        self::ROLE_ADMIN 		=> [],
        self::ROLE_MODERATOR 	=> [],
        self::ROLE_USER 		=> []
    ];
	public function getForcedFilters(User $user): array {
        $options = [];
        // ROLE
        if ($user->get('role') == 'user') {
            $options['role'] = 'user';
        } 
        return $options;
    }
	public function canUserCallMethod(string $method, User $user, ?User $targetUser = null ): bool
    {
        if (!method_exists($this, $method)) {
            return false;
        }
        return $this->$method($user, $targetUser);
	}
	public function canUserManageUser(User $activeUser, User $targetUser): bool
    {
        $actingRole 		= $activeUser->get('role');
        $actingRoleIndex 	= array_search($actingRole, array_keys(self::ROLE_HIERARCHY));
        $targetRole 		= $targetUser->get('role');
        $targetRoleIndex 	= array_search($targetRole, array_keys(self::ROLE_HIERARCHY));

        if ($actingRoleIndex === false || $targetRoleIndex === false) {
            return false;
        }

        if ($actingRoleIndex > $targetRoleIndex) {
            return false;
        }

		return true;
    }
	private function index(User $user) {
		return true;
	}
	private function get(User $user, User $targetUser) {
		return true;
	}
	private function patch(User $user, User $targetUser) {
        if ($user->get('role') != 'admin') {
            if ($user->get('id') !== $targetUser->get('id')) return false;
        }
		return true;
	}
	private function create(User $user): bool 
    {
        if ($user->get('role') != 'admin') return false;
		return true;
	}
	private function delete(User $user, User $targetUser)
    {
        if ($user->get('role') != 'admin') {
            if ($user->get('id') !== $targetUser->get('id')) return false;
        }
		return true;
	}
}