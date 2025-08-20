<?php
namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\TokenService;
use App\Service\UserAuthorizationService;
use Doctrine\ORM\EntityManagerInterface;

use App\Exception\AuthException;
use App\Exception\BusinessException;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class UserService
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly UserAuthorizationService $userAuthService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TokenService $tokenService,
        private readonly EmailService $emailService
    ) {}
    
    public function get(int $id): ?User
    {
        $options = ["id" => $id];
        return $this->userRepo->findOneBy($options);
    }
    public function getByEmail(string $email): ?User
    {
        $options = ["email" => $email];
        return $this->userRepo->findOneBy($options);
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
            throw new BusinessException('USER_CREATION_FAILED', 'UNIQUE_CONSTRAINT');
        }
        // Create temporary token
        $token = $this->tokenService->createToken('activate-account', $user);
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
                $this->entityManager->flush();
                break;
            case 'email':
                $user->setEmail($value);
                $this->entityManager->flush();
                break;
        }
        return $user;
    }
    public function delete(User $user): User
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
        return $user;
    }
    
    public function login(string $username, string $password): array
    {
        $user   = $this->userRepo->findOneBy(['username'=>$username]);
        if (!$user) {
            throw new AuthException('BAD_CREDENTIALS');
        } elseif (!$this->userAuthService->verifyPassword($user->get('password'), $password)) {
            throw new AuthException('BAD_CREDENTIALS', 'BAD_PASSWORD');
        }
        // Stops users that fail business rules from logging in
        $this->userAuthService->applyAccessControl($user);
        //
        $token = $this->tokenService->createSessionJwt($user);
        return ['token' => $token, 'user' => $user->toArray()];
    }
    public function forgotPassword(string $email): void
    {
        $user = $this->userRepo->findOneBy(['email' =>$email]);
        if ($user && $user->get('status') === 'active') {
            // Create temporary token
            $token = $this->tokenService->createToken('forgot-password', $user);
            // Send email; Should be done on a queue so timing is not a factor in this response
            $this->emailService->sendPasswordResetEmail($user->get('email'), $user->get('username'), $token);
        }
    }
    public function resetPassword(string $token, string $password): void
    {
        $user = $this->tokenService->verifyToken($token, 'forgot-password');
        $this->patch([
            'user'      => $user,
            'property'  => 'password',
            'value'     => $password
        ]);
    }
    public function resendActivation(string $email): void
    {
        $user = $this->userRepo->findOneBy(['email'=>$email]);
        if ($user && $user->get('status') === 'pending') {
            // Create temporary token
            $token = $this->tokenService->createToken('activate-account', $user);
            // Send email; Should be done on a queue so timing is not a factor in this response
            $this->emailService->sendWelcomeEmail($user->get('email'), $user->get('username'), $token);
        }
    }
    public function activateAccount(string $token): void
    {
        $user = $this->tokenService->verifyToken($token, 'activate-account');
        $user->activate();
        $this->entityManager->flush();
    }
}
