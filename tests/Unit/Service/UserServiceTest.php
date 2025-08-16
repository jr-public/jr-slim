<?php
namespace Tests\Unit\Service;

use App\Entity\User;
use App\Exception\AuthException;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\TokenService;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;


class UserServiceTest extends TestCase
{
    private UserService $userService;
    private UserRepository|MockObject $userRepositoryMock;
    private EntityManagerInterface|MockObject $entityManagerMock;
    private TokenService|MockObject $tokenServiceMock;
    private EmailService|MockObject $emailServiceMock;

    protected function setUp(): void
    {
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->tokenServiceMock = $this->createMock(TokenService::class);
        $this->emailServiceMock = $this->createMock(EmailService::class);
        
        $this->userService = new UserService(
            $this->userRepositoryMock,
            $this->entityManagerMock,
            $this->tokenServiceMock,
            $this->emailServiceMock
        );
    }

	public function testLoginWithValidCredentialsReturnsTokenAndUserData(): void
    {
        $password = 'correct_password';
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $userData = [
            'id'        => 456,
            'username'  => 'testuser',
            'email'     => 'test@example.com'
        ];
        $userMock = $this->createMock(User::class);
        $userMock->method('toArray')->willReturn($userData);
        $userMock->method('get')->willReturnMap([
            ['id', $userData['id']],
            ['username', $userData['username']],
            ['email', $userData['email']],
            ['password', $hashedPassword],
        ]);        

        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'testuser'])
            ->willReturn($userMock);

        $expectedToken = 'mock_jwt_token_12345';
        $this->tokenServiceMock
            ->expects($this->once())
            ->method('create')
            ->with([
                'sub' => $userData['id'],
                'type' => 'session'
            ])
            ->willReturn($expectedToken);

        $result = $this->userService->login($userData['username'], $password);

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

        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('BAD_CREDENTIALS');
        $this->userService->login('testuser', 'correct_password');
    }
    public function testLoginWithInvalidPasswordThrowsException(): void
    {
        $userMock = $this->createMock(User::class);
        $userMock->method('get')->willReturnMap([
            ['password', password_hash('correct_password', PASSWORD_DEFAULT)],
        ]);
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'testuser'])
            ->willReturn($userMock);

        try {
            $this->userService->login('testuser', 'wrong_password');
            $this->fail('AuthException was not thrown with invalid password.');
        } catch (AuthException $e) {
            $this->assertEquals('BAD_CREDENTIALS', $e->getMessage());
            $this->assertEquals('BAD_PASSWORD', $e->getDetail());
        }
    }

    /**
     * Helper method to create a mock User for testing
     */
    private function createMockUser(int $id): User|MockObject
    {
        $userMock = $this->createMock(User::class);
        $username = 'testuser'.$id;
        $userMock
            ->method('get')
            ->willReturnMap([
                ['id', $id],
                ['username', $username],
                ['email', $username.'@example.com']
            ]);

        return $userMock;
    }

    public function testGetReturnsUserByIdSuccessfully(): void
    {
        // Arrange
        $userId = 123;
        $expectedUser = $this->createMockUser($userId);
        
        $this->userRepositoryMock
            ->expects($this->once())
            ->method('findOneByFilters')
            ->with(['id' => $userId])
            ->willReturn($expectedUser);

        // Act
        $result = $this->userService->get($userId);

        // Assert
        $this->assertSame($expectedUser, $result);
    }

    public function testListReturnsArrayOfUsers(): void
    {
        // Arrange
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
        // Act
        $result = $this->userService->list($options);
        // Assert
        $this->assertSame($expectedUsers, $result);
    }


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
            ->method('persist')
            ->with($userMock);
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
            ->method('persist')
            ->with($userMock);
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
            ->expects($this->once())
            ->method('persist')
            ->with($userMock);
        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');
        // Act
        $result = $this->userService->patch($patchData);
        // Assert
        $this->assertSame($userMock, $result);
    }

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
}