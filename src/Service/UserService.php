<?php
namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class UserService
{
    private readonly UserRepository $userRepo;
    private readonly EntityManagerInterface $entityManager;
    public function __construct(UserRepository $userRepo, EntityManagerInterface $entityManager)
    {
        $this->userRepo = $userRepo;
        $this->entityManager = $entityManager;
    }
    // All select type queries should be filtered depending on the client and also the user role
    public function get(int $id): User {
        $options = ["id" => $id];
        return $this->userRepo->findOneByFilters($options);
    }
    public function list(array $options = []): array {
        return $this->userRepo->findByFilters($options);
    }

    public function create(array $data): User {
        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setPassword($data['password']);
        $user->setClient($data['client']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }
    public function patch(array $data): User {
        $user       = $data['user'];
        $property   = $data['property'];
        $value      = $data['value'];
        switch ($property) {
            case 'password':
                $user->setPassword($value);
                break;
            case 'email':
                $user->setEmail($value);
                break;
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return $user;
    }
    public function delete(User $user): User {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
        return $user;
    }
}
