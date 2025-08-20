<?php

namespace Tests\Unit\Middleware;

use App\Entity\User;
use App\Exception\AuthException;
use App\Middleware\AuthenticationMiddleware;
use App\Repository\UserRepository;
use App\Service\TokenService;
use App\Service\UserAuthorizationService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

class AuthenticationMiddlewareTest extends TestCase
{
    private AuthenticationMiddleware $authenticationMiddleware;
    private UserRepository|MockObject $userRepositoryMock;
    private TokenService|MockObject $tokenServiceMock;
    private UserAuthorizationService|MockObject $userAuthorizationServiceMock;
    private ServerRequestInterface|MockObject $requestMock;
    private RequestHandlerInterface|MockObject $handlerMock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userRepositoryMock = $this->createMock(UserRepository::class);
        $this->tokenServiceMock = $this->createMock(TokenService::class);
        $this->userAuthorizationServiceMock = $this->createMock(UserAuthorizationService::class);
        $this->requestMock = $this->createMock(ServerRequestInterface::class);
        $this->handlerMock = $this->createMock(RequestHandlerInterface::class);

        $this->authenticationMiddleware = new AuthenticationMiddleware(
            $this->userRepositoryMock,
            $this->tokenServiceMock,
            $this->userAuthorizationServiceMock
        );
    }
    // isAuthRoute()
    public function testIsAuthRouteWithGuestRouteReturnsFalse(): void
    {
        $this->assertFalse($this->authenticationMiddleware->isAuthRoute('/guest/foo/bar'));
    }

    public function testIsAuthRouteWithAuthRouteReturnsTrue(): void
    {
        $this->assertTrue($this->authenticationMiddleware->isAuthRoute('/user/foo/bar'));
    }

    public function testIsAuthRouteWithEmptyPathReturnsTrue(): void
    {
        $this->assertTrue($this->authenticationMiddleware->isAuthRoute(''));
    }
    // getBearerToken()
    public function testGetBearerTokenWithCorrectFormatReturnsToken(): void
    {
        $token = $this->authenticationMiddleware->getBearerToken('Bearer my-secret-token');
        $this->assertEquals('my-secret-token', $token);
    }
    public function testGetBearerTokenWithEmptyHeaderThrowsException(): void
    {
        try {
            $this->authenticationMiddleware->getBearerToken('');
            $this->fail('Should have thrown an exception');
        } catch (AuthException $th) {
            $this->assertEquals('AUTHENTICATION_FAILED', $th->getMessage());
            $this->assertEquals('BAD_TOKEN', $th->getDetail());
        }
    }
    public function testGetBearerTokenWithIncorrectFormatThrowsException(): void
    {
        try {
            $this->authenticationMiddleware->getBearerToken('Token my-secret-token');
            $this->fail('Should have thrown an exception');
        } catch (AuthException $th) {
            $this->assertEquals('AUTHENTICATION_FAILED', $th->getMessage());
            $this->assertEquals('BAD_TOKEN', $th->getDetail());
        }
    }
    // process()
    public function testProcessWithGuestRouteSkipsAuthentication(): void
    {
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn('/guest/login');
        $this->requestMock->method('getUri')->willReturn($uriMock);

        $responseMock = $this->createMock(ResponseInterface::class);
        $this->handlerMock
            ->expects($this->once())
            ->method('handle')
            ->with($this->requestMock)
            ->willReturn($responseMock);

        $this->tokenServiceMock->expects($this->never())->method('decodeSessionJwt');
        $this->userRepositoryMock->expects($this->never())->method('findOneBy');

        $response = $this->authenticationMiddleware->process($this->requestMock, $this->handlerMock);

        $this->assertSame($responseMock, $response);
    }
    public function testProcessWithEmptyHeaderThrowsException(): void
    {
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn('/auth/secure');
        $this->requestMock->method('getUri')->willReturn($uriMock);
        $this->requestMock->method('getHeaderLine')->with('Authorization')->willReturn('');

        try {
            $this->authenticationMiddleware->process($this->requestMock, $this->handlerMock);
            $this->fail('Should have thrown an exception');
        } catch (AuthException $th) {
            $this->assertEquals('AUTHENTICATION_FAILED', $th->getMessage());
            $this->assertEquals('BAD_TOKEN', $th->getDetail());
        }
    }
    public function testProcessWithInvalidUserThrowsException(): void
    {
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn('/api/secure');
        $this->requestMock->method('getUri')->willReturn($uriMock);
        $this->requestMock->method('getHeaderLine')->with('Authorization')->willReturn('Bearer valid-token');

        $decodedToken = new \stdClass();
        $decodedToken->sub = 123;
        $this->tokenServiceMock->method('decodeSessionJwt')->with('valid-token')->willReturn($decodedToken);
        $this->userRepositoryMock->method('findOneBy')->with(['id' => 123])->willReturn(null);

        $this->userAuthorizationServiceMock
            ->method('applyAccessControl')
            ->willThrowException(new AuthException('BAD_USER'));
        try {
            $this->authenticationMiddleware->process($this->requestMock, $this->handlerMock);
            $this->fail('Should have thrown an exception');
        } catch (AuthException $th) {
            $this->assertEquals('AUTHENTICATION_FAILED', $th->getMessage());
            $this->assertEquals('BAD_USER', $th->getDetail());
        }
    }
    public function testProcessWithInactiveUserThrowsException(): void
    {
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn('/api/secure');
        $this->requestMock->method('getUri')->willReturn($uriMock);
        $this->requestMock->method('getHeaderLine')->with('Authorization')->willReturn('Bearer valid-token');

        $decodedToken = new \stdClass();
        $decodedToken->sub = 123;
        $this->tokenServiceMock->method('decodeSessionJwt')->with('valid-token')->willReturn($decodedToken);

        $userMock = $this->createMock(User::class);
        $this->userRepositoryMock->method('findOneBy')->with(['id' => 123])->willReturn($userMock);

        $this->userAuthorizationServiceMock
            ->method('applyAccessControl')
            ->willThrowException(new AuthException('NOT_ACTIVE'));
        try {
            $this->authenticationMiddleware->process($this->requestMock, $this->handlerMock);
            $this->fail('Should have thrown an exception');
        } catch (AuthException $th) {
            $this->assertEquals('AUTHENTICATION_FAILED', $th->getMessage());
            $this->assertEquals('NOT_ACTIVE', $th->getDetail());
        }
    }
    public function testProcessWithValidUserSucceeds(): void
    {
        $uriMock = $this->createMock(UriInterface::class);
        $uriMock->method('getPath')->willReturn('/api/secure');
        $this->requestMock->method('getUri')->willReturn($uriMock);
        $this->requestMock->method('getHeaderLine')->with('Authorization')->willReturn('Bearer valid-token');

        $decodedToken = new \stdClass();
        $decodedToken->sub = 123;
        $this->tokenServiceMock->method('decodeSessionJwt')->with('valid-token')->willReturn($decodedToken);

        $userMock = $this->createMock(User::class);
        $this->userRepositoryMock->method('findOneBy')->with(['id' => 123])->willReturn($userMock);

        $this->userAuthorizationServiceMock->expects($this->once())->method('applyAccessControl')->with($userMock);

        $requestWithUser = $this->createMock(ServerRequestInterface::class);
        $this->requestMock->expects($this->once())
            ->method('withAttribute')
            ->with('active_user', $userMock)
            ->willReturn($requestWithUser);

        $responseMock = $this->createMock(ResponseInterface::class);
        $this->handlerMock->expects($this->once())
            ->method('handle')
            ->with($requestWithUser)
            ->willReturn($responseMock);

        $response = $this->authenticationMiddleware->process($this->requestMock, $this->handlerMock);

        $this->assertSame($responseMock, $response);
    }
}