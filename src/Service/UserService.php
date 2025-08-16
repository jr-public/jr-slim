<?php
namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;

use App\Exception\AuthException;
use App\Exception\BusinessException;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class UserService
{
    private readonly UserRepository $userRepo;
    private readonly EntityManagerInterface $entityManager;
    private readonly TokenService $tokenService;
    private readonly EmailService $emailService;

    public function __construct(
        UserRepository $userRepo, 
        EntityManagerInterface $entityManager, 
        TokenService $tokenService,
        EmailService $emailService
    )
    {
        $this->userRepo = $userRepo;
        $this->entityManager = $entityManager;
        $this->tokenService = $tokenService;
        $this->emailService = $emailService;
    }
    
    public function login(string $username, string $password): array
    {
        $user   = $this->userRepo->findOneBy(['username'=>$username]);
        if (!$user) {
            throw new AuthException('BAD_CREDENTIALS');
        } elseif (!password_verify($password, $user->get('password'))) { // 
            throw new AuthException('BAD_CREDENTIALS', 'BAD_PASSWORD');
        }
        $token  = $this->tokenService->create([
            'sub'       => $user->get('id'),
            'type'      => 'session'
        ]);
        return ['token' => $token, 'user' => $user->toArray()];
    }
    public function get(int $id): ?User
    {
        $options = ["id" => $id];
        return $this->userRepo->findOneByFilters($options);
    }
    public function getByEmail(string $email): ?User
    {
        $options = ["email" => $email];
        return $this->userRepo->findOneByFilters($options);
    }
    public function list(array $options = []): array
    {
        return $this->userRepo->findByFilters($options);
    }

    public function create(array $data): User
    {
        try {
            $user = new User();
            $user->setUsername($data['username']);
            $user->setEmail($data['email']);
            $user->setPassword($data['password']);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $th) {
            throw new BusinessException('UNIQUE_CONSTRAINT', $th->getMessage());
        }
        // Create temporary token
        $token  = $this->tokenService->create([
            'sub'   => $user->get('id'),
            'type'  => 'activate-account'
        ], 30);
        // Send email; Should be done on a queue so timing is not a factor in this response
        $this->emailService->sendWelcomeEmail($user->get('email'), $user->get('username'), $token);
        return $user;
    }
    public function patch(array $data): User
    {
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
    public function delete(User $user): User
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
        return $user;
    }
    public function forgotPassword(string $email): void
    {
        $user = $this->getByEmail($email);
        if ($user && $user->get('status') === 'active') {
            // Create temporary token
            $token  = $this->tokenService->create([
                'sub'   => $user->get('id'),
                'type'  => 'forgot-password'
            ], 30);
            // Send email; Should be done on a queue so timing is not a factor in this response
            $this->emailService->sendPasswordResetEmail($user->get('email'), $user->get('username'), $token);
        }
    }
    public function resetPassword(string $token, string $password): void
    {
        $user = $this->tokenService->verify($token, 'forgot-password');
        $this->patch([
            'user'      => $user,
            'property'  => 'password',
            'value'     => $password
        ]);
    }
    public function resendActivation(string $email): void
    {
        $user = $this->getByEmail($email);
        if ($user && $user->get('status') === 'pending') {
            // Create temporary token
            $token  = $this->tokenService->create([
                'sub'   => $user->get('id'),
                'type'  => 'activate-account'
            ], 30);
            // Send email; Should be done on a queue so timing is not a factor in this response
            $this->emailService->sendWelcomeEmail($user->get('email'), $user->get('username'), $token);
        }
    }
    public function activateAccount(string $token): void
    {
        $user = $this->tokenService->verify($token, 'activate-account');
        $user->activate();
        $this->entityManager->flush();
    }
}
