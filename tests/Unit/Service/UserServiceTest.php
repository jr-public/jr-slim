<?php
namespace Tests\Unit\Service;

use App\Entity\User;
use App\Exception\AuthException;
use App\Exception\BusinessException;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\TokenService;
use App\Service\UserAuthorizationService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;


class UserServiceTest extends TestCase
{
    private UserService $userService;
    private UserRepository|MockObject $userRepositoryMock;
    private UserAuthorizationService|MockObject $userAuthServiceMock;
    private EntityManagerInterface|MockObject $entityManagerMock;
    private TokenService|MockObject $tokenServiceMock;
    private EmailService|MockObject $emailServiceMock;
    private readonly string $correct_password;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->userAuthServiceMock = $this->createMock(UserAuthorizationService::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->tokenServiceMock = $this->createMock(TokenService::class);
        $this->emailServiceMock = $this->createMock(EmailService::class);
        
        $this->userService = new UserService(
            $this->userRepositoryMock,
            $this->userAuthServiceMock,
            $this->entityManagerMock,
            $this->tokenServiceMock,
            $this->emailServiceMock
        );

        $this->correct_password = 'correct_password';
    }
    /**
     * Helper method to create a mock User for testing
     */
    private function createMockUser(int $id): User|MockObject
    {
        $hashedPassword = password_hash($this->correct_password, PASSWORD_BCRYPT);
        $userData = [
            'id'        => $id,
            'username'  => 'testuser'.$id,
            'email'     => 'testuser'.$id.'@example.com',
        ];
        $userMock = $this->createMock(User::class);
        $userMock->method('toArray')->willReturn($userData);
        $userMock->method('get')->willReturnMap([
            ['id', $userData['id']],
            ['username', $userData['username']],
            ['email', $userData['email']],
            ['password', $hashedPassword],
        ]);    
        return $userMock;
    }
    /**
     * Login testing methods
     */
	public function testLoginWithValidCredentialsReturnsTokenAndUserData(): void
    {
        $userMock = $this->createMockUser(123);
        $userData = $userMock->toArray();

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => $userData['username']])
            ->willReturn($userMock);

        $this->userAuthServiceMock
            ->expects($this->once())
            ->method('verifyPassword')
            ->willReturn(true);

        $this->userAuthServiceMock
            ->expects($this->once())
            ->method('applyAccessControl')
            ->with($userMock);

        $expectedToken = 'mock_jwt_token_12345';
        $this->tokenServiceMock
            ->expects($this->once())
            ->method('createSessionJwt')
            ->willReturn($expectedToken);

        $result = $this->userService->login($userData['username'], $this->correct_password);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($expectedToken, $result['token']);
        $this->assertEquals($userData, $result['user']);
    }
    public function testLoginWithNonExistentUserThrowsException(): void
    {
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'testuser'])
            ->willReturn(null);

        $this->userAuthServiceMock
            ->expects($this->never())
            ->method('verifyPassword');

        $this->userAuthServiceMock
            ->expects($this->never())
            ->method('applyAccessControl');

        $this->tokenServiceMock
            ->expects($this->never())
            ->method('createSessionJwt');

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('BAD_CREDENTIALS');
        $this->userService->login('testuser', '');
    }
    public function testLoginWithWrongPasswordThrowsException(): void
    {
        $userMock = $this->createMockUser(123);
        $userData = $userMock->toArray();

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => $userData['username']])
            ->willReturn($userMock);

        $this->userAuthServiceMock
            ->expects($this->once())
            ->method('verifyPassword')
            ->willReturn(false);

        $this->userAuthServiceMock
            ->expects($this->never())
            ->method('applyAccessControl');

        $this->tokenServiceMock
            ->expects($this->never())
            ->method('createSessionJwt');

        try {
            $this->userService->login($userData['username'], 'wrong_password');
            $this->fail('AuthException was not thrown with invalid password.');
        } catch (AuthException $e) {
            $this->assertEquals('BAD_CREDENTIALS', $e->getMessage());
            $this->assertEquals('BAD_PASSWORD', $e->getDetail());
        }
    }
    public function testLoginWithNotActiveUserThrowsException(): void
    {
        $hashedPassword = password_hash($this->correct_password, PASSWORD_BCRYPT);
        $id = 123;
        $userData = [
            'id'        => $id,
            'username'  => 'testuser'.$id,
            'email'     => 'testuser'.$id.'@example.com',
        ];
        $userMock = $this->createMock(User::class);
        $userMock->method('toArray')->willReturn($userData);
        $userMock->method('get')->willReturnMap([
            ['username', $userData['username']],
            ['password', $hashedPassword],
            ['status', 'pending'],
        ]); 

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => $userData['username']])
            ->willReturn($userMock);

        $this->userAuthServiceMock
            ->expects($this->once())
            ->method('verifyPassword')
            ->willReturn(true);

        $this->userAuthServiceMock
            ->expects($this->once())
            ->method('applyAccessControl')
            ->with($userMock)
            ->willThrowException(new AuthException('NOT_ACTIVE'));

        $this->tokenServiceMock
            ->expects($this->never())
            ->method('createSessionJwt');

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('NOT_ACTIVE');

        $this->userService->login($userData['username'], $this->correct_password);
    }
    /**
     * Get testing methods
     */
    public function testGetWithExistingIdReturnsUserSuccessfully(): void
    {
        $userId = 123;
        $expectedUser = $this->createMockUser($userId);
        
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $userId])
            ->willReturn($expectedUser);

        $result = $this->userService->get($userId);

        $this->assertSame($expectedUser, $result);
    }
    public function testGetWithWrongIdReturnsNull(): void
    {
        $userId = 123;
        
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $userId])
            ->willReturn(null);

        $result = $this->userService->get($userId);

        $this->assertNull($result);
    }
    /**
     * Get by email testing methods
     */
    public function testGetByEmailWithExistingEmailReturnsUserSuccessfully(): void
    {
        $userId = 123;
        $userMock = $this->createMockUser($userId);
        $userData = $userMock->toArray();
        
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $userData['email']])
            ->willReturn($userMock);

        $result = $this->userService->getByEmail($userData['email']);

        $this->assertSame($userMock, $result);
    }
    public function testGetByEmailWithWrongEmailReturnsNull(): void
    {
        $userEmail = '';
        
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $userEmail])
            ->willReturn(null);

        $result = $this->userService->getByEmail($userEmail);

        $this->assertNull($result);
    }

    /**
     * List testing methods
     */
    public function testListReturnsArrayOfUsers(): void
    {
        $options = [];
        $expectedUsers = [
            $this->createMockUser(1),
            $this->createMockUser(2)
        ];
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findByFilters')
            ->with($options)
            ->willReturn($expectedUsers);

        $result = $this->userService->list($options);

        $this->assertSame($expectedUsers, $result);
    }
    public function testListPassesFilterToRepository(): void
    {
        $userMock = $this->createMockUser(42);
        $userData = $userMock->toArray();
        $expectedUsers = [
            $userMock
        ];
        $options = ['id' => $userData['id']];
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findByFilters')
            ->with($options)
            ->willReturn($expectedUsers);

        $result = $this->userService->list($options);

        $this->assertSame($expectedUsers, $result);
    }
    public function testListThrowsWhenRepositoryThrows(): void
    {
        $options = [];

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findByFilters')
            ->with($options)
            ->willThrowException(new \RuntimeException('Repository failure'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Repository failure');

        $this->userService->list($options);
    }
    /**
     * Create testing methods
     */
    public function testCreateSuccessfullyPersistsUserAndSendsEmail(): void
    {
        $userMock = $this->createMockUser(42);
        $userData = $userMock->toArray();
        $creationData = array_merge($userData, ['password' => $this->correct_password]);
        $fakeToken = 'fake-activation-token';

        // Mock the EntityManager
        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(User::class));
        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');

        // Mock TokenService
        $this->tokenServiceMock
            ->expects($this->once())
            ->method('createToken')
            ->willReturn($fakeToken);

        // Mock EmailService
        $this->emailServiceMock
            ->expects($this->once())
            ->method('sendWelcomeEmail')
            ->with(
                $userData['email'],
                $userData['username'],
                $fakeToken
            );

        // Call the service
        $user = $this->userService->create($creationData);
        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals($userData['username'], $user->get('username'));
        $this->assertEquals($userData['email'], $user->get('email'));
    }
    public function testCreateThrowsBusinessExceptionOnUniqueConstraint(): void
    {
        $userMock = $this->createMockUser(42);
        $data = [
            'username' => 'testuser42',
            'email'    => 'testuser42@example.com',
            'password' => $this->correct_password,
        ];

        // Make the EntityManager throw UniqueConstraintViolationException on persist
        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->willThrowException(new BusinessException('USER_CREATION_FAILED', 'UNIQUE_CONSTRAINT'));

        $this->entityManagerMock
            ->expects($this->never())
            ->method('flush');

        // TokenService and EmailService should never be called
        $this->tokenServiceMock->expects($this->never())->method('createToken');
        $this->emailServiceMock->expects($this->never())->method('sendWelcomeEmail');

        // Call the service
        try {
            $this->userService->create($data);
            $this->fail('BusinessException was not thrown with repeating username.');
        } catch (BusinessException $e) {
            $this->assertEquals('USER_CREATION_FAILED', $e->getMessage());
            $this->assertEquals('UNIQUE_CONSTRAINT', $e->getDetail());
        }
    }
    /**
     * Patch testing methods
     */
    public function testPatchUserPasswordSuccessfully(): void
    {
        // Arrange
        $userMock = $this->createMock(User::class);
        $patchData = [
            'user' => $userMock,
            'property' => 'password',
            'value' => 'newpassword123'
        ];
        $userMock
            ->expects($this->once())
            ->method('setPassword')
            ->with('newpassword123');
        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');
        // Act
        $result = $this->userService->patch($patchData);
        // Assert
        $this->assertSame($userMock, $result);
    }
    public function testPatchUserEmailSuccessfully(): void
    {
        // Arrange
        $userMock = $this->createMock(User::class);
        $patchData = [
            'user' => $userMock,
            'property' => 'email',
            'value' => 'newemail@example.com'
        ];
        $userMock
            ->expects($this->once())
            ->method('setEmail')
            ->with('newemail@example.com');
        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');
        // Act
        $result = $this->userService->patch($patchData);
        // Assert
        $this->assertSame($userMock, $result);
    }
    public function testPatchUserWithUnsupportedPropertyDoesNothing(): void
    {
        // Arrange
        $userMock = $this->createMock(User::class);
        $patchData = [
            'user' => $userMock,
            'property' => 'unsupported_property',
            'value' => 'some_value'
        ];
        // The user mock should not receive any method calls for unsupported properties
        $userMock
            ->expects($this->never())
            ->method('setPassword');
        $userMock
            ->expects($this->never())
            ->method('setEmail');
        $this->entityManagerMock
            ->expects($this->never())
            ->method('flush');
        // Act
        $result = $this->userService->patch($patchData);
        // Assert
        $this->assertSame($userMock, $result);
    }
    /**
     * Delete testing methods
     */
    public function testDeleteUserSuccessfully(): void
    {
        // Arrange
        $userMock = $this->createMock(User::class);
        $this->entityManagerMock
            ->expects($this->once())
            ->method('remove')
            ->with($userMock);
        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');
        // Act
        $result = $this->userService->delete($userMock);
        // Assert
        $this->assertSame($userMock, $result);
    }
    /**
     * Forgot password testing methods
     */
    public function testForgotPasswordSendsEmailWhenUserIsActive(): void
    {
        $fakeToken = 'fake-reset-token';
        $id = 123;
        $userData = [
            'id'        => $id,
            'username'  => 'testuser'.$id,
            'email'     => 'testuser'.$id.'@example.com',
        ];
        $userMock = $this->createMock(User::class);
        $userMock->method('toArray')->willReturn($userData);
        $userMock->method('get')->willReturnMap([
            ['id', $userData['id']],
            ['username', $userData['username']],
            ['email', $userData['email']],
            ['status', 'active'],
        ]); 

        // Mock repository
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $userData['email']])
            ->willReturn($userMock);

        // Mock token service
        $this->tokenServiceMock
            ->expects($this->once())
            ->method('createToken')
            ->willReturn($fakeToken);

        // Mock email service
        $this->emailServiceMock
            ->expects($this->once())
            ->method('sendPasswordResetEmail')
            ->with(
                $userData['email'],
                $userData['username'],
                $fakeToken
            );

        // Act
        $this->userService->forgotPassword($userData['email']);
    }
    public function testForgotPasswordDoesNothingWhenUserIsNotActive(): void
    {
        $userMock = $this->createMockUser(42);
        $userMock->method('get')->willReturnMap([
            ['status', 'pending'],
        ]);
        $userData = $userMock->toArray();

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $userData['email']])
            ->willReturn($userMock);

        $this->tokenServiceMock->expects($this->never())->method('createToken');
        $this->emailServiceMock->expects($this->never())->method('sendPasswordResetEmail');

        $this->userService->forgotPassword($userData['email']);
    }
    public function testForgotPasswordDoesNothingWhenUserDoesNotExist(): void
    {
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => ''])
            ->willReturn(null);

        $this->tokenServiceMock->expects($this->never())->method('createToken');
        $this->emailServiceMock->expects($this->never())->method('sendPasswordResetEmail');

        $this->userService->forgotPassword('');
    }
    /**
     * Reset password testing methods
     */
    public function testResetPasswordSuccessfully(): void
    {
        $newPassword = 'newpassword123';
        $userMock = $this->createMockUser(42);

        $this->tokenServiceMock
            ->expects($this->once())
            ->method('verifyToken')
            ->with('fake-reset-token', 'forgot-password')
            ->willReturn($userMock);

        $userMock->expects($this->once())
             ->method('setPassword')
             ->with($newPassword);
        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');
        $this->userService->resetPassword('fake-reset-token', $newPassword);
    }
    public function testResetPasswordWithWrongTokenDoesNothing(): void
    {
        $this->tokenServiceMock
            ->expects($this->once())
            ->method('verifyToken')
            ->with('', 'forgot-password')
            ->willThrowException(new BusinessException('TOKEN_INVALID'));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('TOKEN_INVALID');

        $this->userService->resetPassword('', '');
    }
    /**
     * Resend activation email testing methods
     */
    public function testResendActivationSendsEmailWhenUserIsPending(): void
    {
        $fakeToken = 'fake-token';
        $id = 123;
        $userData = [
            'id'        => $id,
            'username'  => 'testuser'.$id,
            'email'     => 'testuser'.$id.'@example.com',
        ];
        $userMock = $this->createMock(User::class);
        $userMock->method('toArray')->willReturn($userData);
        $userMock->method('get')->willReturnMap([
            ['id', $userData['id']],
            ['username', $userData['username']],
            ['email', $userData['email']],
            ['status', 'pending'],
        ]); 

        // Mock repository
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $userData['email']])
            ->willReturn($userMock);

        // Mock token service
        $this->tokenServiceMock
            ->expects($this->once())
            ->method('createToken')
            ->willReturn($fakeToken);

        // Mock email service
        $this->emailServiceMock
            ->expects($this->once())
            ->method('sendWelcomeEmail')
            ->with(
                $userData['email'],
                $userData['username'],
                $fakeToken
            );

        // Act
        $this->userService->resendActivation($userData['email']);
    }
    public function testResendActivationDoesNothingWhenUserDoesNotExist(): void
    {
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => ''])
            ->willReturn(null);

        $this->tokenServiceMock->expects($this->never())->method('createToken');
        $this->emailServiceMock->expects($this->never())->method('sendWelcomeEmail');

        $this->userService->resendActivation('');
    }
    public function testResendActivationDoesNothingWhenUserIsNotPending(): void
    {
        $userMock = $this->createMockUser(42);
        $userMock->method('get')->willReturnMap([
            ['status', 'active'],
        ]);
        $userData = $userMock->toArray();

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $userData['email']])
            ->willReturn($userMock);

        $this->tokenServiceMock->expects($this->never())->method('createToken');
        $this->emailServiceMock->expects($this->never())->method('sendWelcomeEmail');

        $this->userService->resendActivation($userData['email']);
    }
    /**
     * Activate account testing methods
     */
    public function testActivateAccountSuccessfully(): void
    {
        $fakeToken = 'fake-token';
        $id = 123;
        $userData = [
            'id'        => $id,
            'username'  => 'testuser'.$id,
            'email'     => 'testuser'.$id.'@example.com',
        ];
        $userMock = $this->createMock(User::class);
        $userMock->method('toArray')->willReturn($userData);
        $userMock->method('get')->willReturnMap([
            ['id', $userData['id']],
            ['username', $userData['username']],
            ['email', $userData['email']],
            ['status', 'pending'],
        ]); 

        $this->tokenServiceMock
            ->expects($this->once())
            ->method('verifyToken')
            ->with($fakeToken, 'activate-account')
            ->willReturn($userMock);
        $userMock->expects($this->once())
             ->method('activate');
        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');

        $this->userService->activateAccount($fakeToken);
    }
    public function testActivateAccountDoesNothingWhenTokenIsInvalid(): void
    {
        $this->tokenServiceMock
            ->expects($this->once())
            ->method('verifyToken')
            ->with('', 'activate-account')
            ->willThrowException(new BusinessException('TOKEN_INVALID'));

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('TOKEN_INVALID');

        $this->userService->activateAccount('');
    }
}