<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\Client;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

use App\Exception\AuthException;

class UserService
{
    private readonly UserRepository $userRepo;
    private readonly EntityManagerInterface $entityManager;
    public function __construct(UserRepository $userRepo, EntityManagerInterface $entityManager)
    {
        $this->userRepo = $userRepo;
        $this->entityManager = $entityManager;
    }
    
    public function login(Client $client, string $username, string $password): User
    {
        $user   = $this->userRepo->findByUsernameAndClient($username, $client->get('id'));
        if (!$user) {
            throw new AuthException('BAD_CREDENTIALS, Invalid username');
        } elseif (!password_verify($password, $user->get('password'))) { // 
            throw new AuthException('BAD_CREDENTIALS, Invalid password'.json_encode([$password, $user->get('password')]));
        }
        return $user;
    }
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
