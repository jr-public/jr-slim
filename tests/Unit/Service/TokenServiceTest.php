<?php

namespace Tests\Unit\Service;

use App\Entity\Token;
use App\Entity\User;
use App\Exception\AuthException;
use App\Service\TokenService;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TokenServiceTest extends TestCase
{
    private TokenService $tokenService;
    private EntityManagerInterface|MockObject $entityManagerMock;
    private string $secret = 'testing_key';
    private string $algorithm = 'HS256';

    protected function setUp(): void
    {
        parent::setUp();
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->tokenService = new TokenService($this->entityManagerMock, $this->secret, $this->algorithm);
    }

    public function testCreateSessionJwtGeneratesValidAndDecodableToken(): void
    {
        $userMock = $this->createMock(User::class);
        $token = $this->tokenService->createSessionJwt($userMock);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);

        // Verify the token can be decoded without an exception
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            $this->assertIsObject($decoded);
        } catch (\Exception $e) {
            $this->fail('JWT decoding failed with an unexpected exception: ' . $e->getMessage());
        }
    }

    public function testCreateSessionJwtHasCorrectPayloadClaims(): void
    {
        $userId = 123;
        $userMock = $this->createMock(User::class);
        $userMock->method('get')->with('id')->willReturn($userId);

        $token = $this->tokenService->createSessionJwt($userMock);
        $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));

        // Assert
        $this->assertObjectHasProperty('sub', $decoded);
        $this->assertObjectHasProperty('type', $decoded);
        $this->assertEquals($userId, $decoded->sub);
        $this->assertEquals('session', $decoded->type);
    }

    public function testCreateSessionJwtCalculatesCorrectTimestamps(): void
    {
        $expirationMinutes = 60;
        $now = (new \DateTimeImmutable())->getTimestamp();
        $userMock = $this->createMock(User::class);
        $userMock->method('get')->with('id')->willReturn(123);

        $token = $this->tokenService->createSessionJwt($userMock, $expirationMinutes);
        $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));

        // Assert
        $this->assertObjectHasProperty('iat', $decoded);
        $this->assertObjectHasProperty('exp', $decoded);

        $expectedExpiration = $now + ($expirationMinutes * 60);

        // The assertion should allow for a small margin of error due to execution time
        $this->assertGreaterThanOrEqual($now - 5, $decoded->iat);
        $this->assertLessThanOrEqual($now + 5, $decoded->iat);
        $this->assertGreaterThanOrEqual($expectedExpiration - 5, $decoded->exp);
        $this->assertLessThanOrEqual($expectedExpiration + 5, $decoded->exp);
    }
    public function testDecodeSessionJwtWithValidTokenReturnsDecodedObject(): void
    {
        $user = $this->createMock(User::class);
        $user->method('get')->with('id')->willReturn(123);
        
        $payload = [
            'sub' => $user->get('id'),
            'type' => 'session',
            'iat' => time(),
            'exp' => time() + 3600
        ];
        $validToken = $this->tokenService->createSessionJwt($user);
        $decodedObject = $this->tokenService->decodeSessionJwt($validToken);

        $this->assertInstanceOf(\stdClass::class, $decodedObject);
        $this->assertEquals($payload['sub'], $decodedObject->sub);
        $this->assertEquals($payload['type'], $decodedObject->type);
        $this->assertEquals($payload['iat'], $decodedObject->iat);
        $this->assertEquals($payload['exp'], $decodedObject->exp);
    }
    public function testDecodeSessionJwtWithExpiredTokenThrowsCorrectException(): void
    {
        $payload = [
            'sub' => 123,
            'type' => 'session',
            'iat' => time() - 3600, // Issued 1 hour ago
            'exp' => time() - 1800  // Expired 30 minutes ago (after iat, but in the past)
        ];
        $badToken = JWT::encode($payload, $this->secret, $this->algorithm);
        try {
            $this->tokenService->decodeSessionJwt($badToken);
            $this->fail('AuthException was not thrown with expired token.');
        } catch (AuthException $e) {
            $this->assertEquals('TOKEN_INVALID', $e->getMessage());
            $this->assertEquals('TOKEN_EXPIRED', $e->getDetail());
        }
    }
    public function testDecodeSessionJwtWithInvalidSignatureThrowsCorrectException(): void
    {
        $payload = [
            'sub' => 123,
            'type' => 'session',
            'iat' => time(),
            'exp' => time() + 3600
        ];
        $invalidSecret = 'a_different_secret';
        $badToken = JWT::encode($payload, $invalidSecret, $this->algorithm);

        try {
            $this->tokenService->decodeSessionJwt($badToken);
            $this->fail('AuthException was not thrown with invalid signature');
        } catch (AuthException $e) {
            $this->assertEquals('TOKEN_INVALID', $e->getMessage());
            $this->assertEquals('TOKEN_SIGNATURE', $e->getDetail());
        }
    }
    public function testDecodeSessionJwtWithInvalidFormatThrowsCorrectException(): void
    {
        $badToken = 'this.is.not.a.valid.jwt';
        try {
            $this->tokenService->decodeSessionJwt($badToken);
            $this->fail('AuthException was not thrown for invalid token format.');
        } catch (AuthException $e) {
            $this->assertEquals('TOKEN_INVALID', $e->getMessage());
            $this->assertEquals('TOKEN_UNEXPECTED_VALUE', $e->getDetail());
        }
    }
    public function testDecodeSessionJwtWhenTypeClaimIsMissingThrowsCorrectException(): void
    {
        $payload = [
            'sub' => 123,
            'iat' => time(),
            'exp' => time() + 3600
        ];
        $badToken = JWT::encode($payload, $this->secret, $this->algorithm);
        try {
            $this->tokenService->decodeSessionJwt($badToken);
            $this->fail('AuthException was not thrown with missing type claim.');
        } catch (AuthException $e) {
            $this->assertEquals('TOKEN_INVALID', $e->getMessage());
            $this->assertEquals('TOKEN_TYPE_REQUIRED', $e->getDetail());
        }
    }
    public function testDecodeSessionJwtWhenTypeIsNotSessionThrowsCorrectException(): void
    {
        $payload = [
            'type' => 'access',
            'sub' => 123,
            'iat' => time(),
            'exp' => time() + 3600
        ];
        $badToken = JWT::encode($payload, $this->secret, $this->algorithm);
        try {
            $this->tokenService->decodeSessionJwt($badToken);
            $this->fail('AuthException was not thrown with invalid type claim');
        } catch (AuthException $e) {
            $this->assertEquals('TOKEN_INVALID', $e->getMessage());
            $this->assertEquals('TOKEN_TYPE_MISMATCH', $e->getDetail());
        }
    }
    public function testDecodeSessionJwtWhenSubClaimIsMissingThrowsCorrectException(): void
    {
        $payload = [
            'type' => 'session',
            'iat' => time(),
            'exp' => time() + 3600
        ];
        $badToken = JWT::encode($payload, $this->secret, $this->algorithm);
        try {
            $this->tokenService->decodeSessionJwt($badToken);
            $this->fail('AuthException was not thrown with invalid type claim');
        } catch (AuthException $e) {
            $this->assertEquals('TOKEN_INVALID', $e->getMessage());
            $this->assertEquals('TOKEN_SUBJECT_REQUIRED', $e->getDetail());
        }
    }
    public function testCreateTokenGeneratesValidPersistentToken(): void
    {
        $expirationMinutes = 60;
        $now        = new \DateTimeImmutable();
        $expiration = $now->modify("$expirationMinutes minutes")->getTimestamp();
        $id         = 'abcdefabcdefabcdef';
        $secret     = 'secretstring';
        $expectedToken = $id . '.' . $secret;
        $tokenType  = 'forgot-password';
        $userMock   = $this->createMock(User::class);
        $userMock->method('get')->with('id')->willReturn(123);

        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function(Token $token) use ($userMock, $tokenType, $expiration) {
                $actualExpiration = $token->get('expires_at')->getTimestamp();
                $this->assertSame($userMock->get('id'), $token->get('user')->get('id'));
                $this->assertSame($tokenType, $token->get('type'));
                $this->assertInstanceOf(\DateTimeImmutable::class, $token->get('expires_at'));
                $this->assertTrue($token->get('expires_at') > new \DateTimeImmutable());
                $this->assertLessThanOrEqual($expiration + 5, $actualExpiration);
                $this->assertGreaterThanOrEqual($expiration - 5, $actualExpiration);
                $this->assertNotEmpty($token->get('token_hash'));
                return true;
            }));
        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');

        $service = $this->getMockBuilder(TokenService::class)
            ->setConstructorArgs([$this->entityManagerMock, $this->secret, $this->algorithm])
            ->onlyMethods(['random'])
            ->getMock();

        $service->method('random')
            ->willReturnOnConsecutiveCalls($id, $secret);

        $token = $service->createToken($tokenType, $userMock, $expirationMinutes);
        $this->assertSame($expectedToken, $token);
    }
    public function testCreateTokenWhenExpirationIsZeroThenTokenExpiresImmediately(): void
    {
        $expirationMinutes = 0;
        $now        = new \DateTimeImmutable();
        $expiration = $now->getTimestamp();
        $id         = 'abcdefabcdefabcdef';
        $secret     = 'secretstring';
        $expectedToken = $id . '.' . $secret;
        $tokenType  = 'forgot-password';
        $userMock   = $this->createMock(User::class);
        $userMock->method('get')->with('id')->willReturn(123);

        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function(Token $token) use ($expiration) {
                $actualExpiration = $token->get('expires_at')->getTimestamp();
                $this->assertLessThanOrEqual($expiration + 5, $actualExpiration);
                $this->assertGreaterThanOrEqual($expiration - 5, $actualExpiration);
                return true;
            }));
        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');

        $service = $this->getMockBuilder(TokenService::class)
            ->setConstructorArgs([$this->entityManagerMock, $this->secret, $this->algorithm])
            ->onlyMethods(['random'])
            ->getMock();

        $service->method('random')
            ->willReturnOnConsecutiveCalls($id, $secret);

        $token = $service->createToken($tokenType, $userMock, $expirationMinutes);
        $this->assertSame($expectedToken, $token);
    }
    public function testCreateTokenWhenExpirationNotProvidedThenDefaultIsApplied(): void
    {
        $expirationMinutes = 30;
        $now        = new \DateTimeImmutable();
        $expiration = $now->modify("$expirationMinutes minutes")->getTimestamp();
        $id         = 'abcdefabcdefabcdef';
        $secret     = 'secretstring';
        $expectedToken = $id . '.' . $secret;
        $tokenType  = 'forgot-password';
        $userMock   = $this->createMock(User::class);
        $userMock->method('get')->with('id')->willReturn(123);

        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function(Token $token) use ($expiration) {
                $actualExpiration = $token->get('expires_at')->getTimestamp();
                $this->assertLessThanOrEqual($expiration + 5, $actualExpiration);
                $this->assertGreaterThanOrEqual($expiration - 5, $actualExpiration);
                return true;
            }));
        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');

        $service = $this->getMockBuilder(TokenService::class)
            ->setConstructorArgs([$this->entityManagerMock, $this->secret, $this->algorithm])
            ->onlyMethods(['random'])
            ->getMock();

        $service->method('random')
            ->willReturnOnConsecutiveCalls($id, $secret);

        $token = $service->createToken($tokenType, $userMock);
        $this->assertSame($expectedToken, $token);
    }
    public function testCreateTokenWhenCalledMultipleTimesThenTokensAreUnique(): void
    {
        $expirationMinutes = 30;
        $tokenType  = 'forgot-password';
        $userMock   = $this->createMock(User::class);
        $userMock->method('get')->with('id')->willReturn(123);

        $id1     = 'aaaaaaaaaaaaaaaa';
        $secret1 = 'secret-one';
        $id2     = 'bbbbbbbbbbbbbbbb';
        $secret2 = 'secret-two';

        $expectedToken1 = $id1 . '.' . $secret1;
        $expectedToken2 = $id2 . '.' . $secret2;

        $this->entityManagerMock
            ->expects($this->exactly(2))
            ->method('persist');

        $this->entityManagerMock
            ->expects($this->exactly(2))
            ->method('flush');

        $service = $this->getMockBuilder(TokenService::class)
            ->setConstructorArgs([$this->entityManagerMock, $this->secret, $this->algorithm])
            ->onlyMethods(['random'])
            ->getMock();

        // Stub random calls across both invocations
        $service->method('random')
            ->willReturnOnConsecutiveCalls($id1, $secret1, $id2, $secret2);

        $token1 = $service->createToken($tokenType, $userMock, $expirationMinutes);
        $token2 = $service->createToken($tokenType, $userMock, $expirationMinutes);

        $this->assertSame($expectedToken1, $token1);
        $this->assertSame($expectedToken2, $token2);
        $this->assertNotSame($token1, $token2, 'Tokens from multiple calls should be unique');
    }

    /**
     * Verify Token testing methods
     */
    public function testVerifyTokenWithValidTokenReturnsUserAndMarksAsUsed(): void
    {
        $tokenType = 'forgot-password';
        $id = 'abcdefabcdefabcdef';
        $secret = 'secretstring';
        $hash = password_hash($secret, PASSWORD_DEFAULT);
        $fullToken = $id . '.' . $secret;

        $userMock = $this->createMock(User::class);

        // Mock token entity
        $tokenEntityMock = $this->createMock(Token::class);
        $tokenEntityMock->method('get')->willReturnMap([
            ['id', $id],
            ['type', $tokenType],
            ['expires_at', new \DateTimeImmutable('+30 minutes')],
            ['used', false],
            ['token_hash', $hash],
            ['user', $userMock],
        ]);
        $tokenEntityMock->expects($this->once())
            ->method('markUsed');

        // Mock query result
        $queryMock = $this->getMockBuilder(\Doctrine\ORM\Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOneOrNullResult'])
            ->getMock();
        $queryMock->method('getOneOrNullResult')->willReturn($tokenEntityMock);

        $qbMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbMock->method('select')->willReturnSelf();
        $qbMock->method('from')->willReturnSelf();
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setMaxResults')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);

        $this->entityManagerMock
            ->method('createQueryBuilder')
            ->willReturn($qbMock);

        $this->entityManagerMock
            ->expects($this->once())
            ->method('flush');

        $service = new TokenService($this->entityManagerMock, $this->secret, $this->algorithm);

        $resultUser = $service->verifyToken($fullToken, $tokenType);

        $this->assertSame($userMock, $resultUser);
    }
    public function testVerifyTokenWithInvalidFormatThrowsException(): void
    {
        $tokenType = 'forgot-password';
        $invalidToken = 'invalid-format-without-dot';

        try {
            $this->tokenService->verifyToken($invalidToken, $tokenType);
            $this->fail('AuthException was not thrown with invalid token format.');
        } catch (AuthException $e) {
            $this->assertEquals('TOKEN_INVALID', $e->getMessage());
            $this->assertEquals('TOKEN_FORMAT', $e->getDetail());
        }
    }
    public function testVerifyTokenWhenTokenNotFoundInDatabaseThrowsException(): void
    {
        $tokenType = 'forgot-password';
        $id = 'abcdefabcdefabcdef';
        $secret = 'secretstring';
        $fullToken = $id . '.' . $secret;

        // Mock query result: no token found
        $queryMock = $this->getMockBuilder(\Doctrine\ORM\Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getOneOrNullResult'])
            ->getMock();
        $queryMock->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn(null);

        // Mock query builder
        $qbMock = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qbMock->method('select')->willReturnSelf();
        $qbMock->method('from')->willReturnSelf();
        $qbMock->method('where')->willReturnSelf();
        $qbMock->method('andWhere')->willReturnSelf();
        $qbMock->method('setMaxResults')->willReturnSelf();
        $qbMock->method('setParameter')->willReturnSelf();
        $qbMock->method('getQuery')->willReturn($queryMock);

        $this->entityManagerMock
            ->method('createQueryBuilder')
            ->willReturn($qbMock);

        try {
            $this->tokenService->verifyToken($fullToken, $tokenType);
            $this->fail('AuthException was not thrown with token not found.');
        } catch (AuthException $e) {
            $this->assertEquals('TOKEN_INVALID', $e->getMessage());
            $this->assertEquals('TOKEN_NOT_FOUND', $e->getDetail());
        }
    }
    public function testRandomDefaultIsUrlSafe(): void
    {
        $token = $this->tokenService->random(32);
        $this->assertIsString($token);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9\-_]+$/', $token);
        $this->assertStringNotContainsString('=', $token); // urlsafe trims '='
    }

    public function testRandomWithHexEncoding(): void
    {
        $token = $this->tokenService->random(16, 'hex');

        $this->assertIsString($token);
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
        $this->assertEquals(32, strlen($token)); // 16 bytes * 2 hex chars
    }

    public function testRandomWithBase64Encoding(): void
    {
        $token = $this->tokenService->random(12, 'base64');

        $this->assertIsString($token);
        $decoded = base64_decode($token, true);
        $this->assertNotFalse($decoded);
        $this->assertEquals(12, strlen($decoded));
    }

    public function testRandomWithRawEncoding(): void
    {
        $token = $this->tokenService->random(8, 'raw');

        $this->assertIsString($token);
        $this->assertEquals(8, strlen($token));
    }

    public function testRandomWithInvalidLengthThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Length must be at least 1');

        $this->tokenService->random(0);
    }

    public function testRandomWithInvalidEncodingThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid encoding: unknown');

        $this->tokenService->random(8, 'unknown');
    }
}