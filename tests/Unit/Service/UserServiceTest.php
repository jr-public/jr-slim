<?php
namespace Tests\Unit\Service;

use App\Entity\User;
use App\Entity\Client;
use App\Repository\UserRepository;
use App\Service\UserService;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class UserServiceTest extends TestCase
{
    private UserService $userService;
    private UserRepository|MockObject $userRepositoryMock;
    private EntityManagerInterface|MockObject $entityManagerMock;

    protected function setUp(): void
    {
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        
        $this->userService = new UserService(
            $this->userRepositoryMock,
            $this->entityManagerMock
        );
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

    public function testCreateUserSuccessfully(): void
    {
        // Arrange
        $clientMock = $this->createMock(Client::class);
        $userData = [
            'username' 	=> 'testuser',
            'email' 	=> 'test@example.com',
            'password' 	=> 'password123',
            'client' 	=> $clientMock
        ];
        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(User::class));
        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');
        // Act
        $result = $this->userService->create($userData);
        // Assert
        $this->assertSame($clientMock, $result->get('client'));
        $this->assertInstanceOf(User::class, $result);
        $this->assertSame($userData['username'], $result->get('username'));
        $this->assertSame($userData['email'], $result->get('email'));
		$this->assertTrue(password_verify($userData['password'], $result->get('password')));
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