<?php
namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserService
{
    private readonly EntityManagerInterface $entityManager;
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    public function create(array $data): User {
        $user = new User();
        $user->setUsername($data['username']);
        $user->setEmail($data['email']);
        $user->setPassword($data['password']);
        $user->setClient($data['client']);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        // Validate (or delegate to validator)
        // Hash password
        // Set default roles/statuses
        // Save to database
        // Trigger events if needed
        return $user;
    }
    public function patch(User $user, string $property, string $value): User {
        if (!$user->has($property)) {
            throw new \Exception("USER SERVICE - PATCH - PROPERTY NOT FOUND");
        }
        if ($property == 'password') {
            $user->setPassword($value);
        } else if ($property == 'email') {
            $user->setEmail($value);
        } else {
            throw new \Exception("USER SERVICE - PATCH - PROPERTY NOT IMPLEMENTED FOR PATCHING");
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
