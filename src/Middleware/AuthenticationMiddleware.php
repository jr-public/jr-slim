<?php
namespace App\Middleware;

use App\Exception\AuthException;
use App\Repository\UserRepository;
use App\Service\TokenService;
use App\Service\UserAuthorizationService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AuthenticationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly TokenService $tokenService,
        private readonly UserAuthorizationService $userAuthorizationService,
        private readonly array $guest_routes = [
            '/guest'
        ]
    ) {}

    public function isAuthRoute(string $path): bool
    {
        foreach ($this->guest_routes as $no_auth_route) {
            if (str_starts_with($path, $no_auth_route)) {
                return false;
            }
        }
        return true;
    }
    public function getBearerToken(string $authHeader): string
    {
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthException('AUTHENTICATION_FAILED', 'BAD_TOKEN');
        }
        $token = substr($authHeader, 7);
        return $token;
    }
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Current route being labeled as "no_auth" means it doesn't require authentication
        if (!$this->isAuthRoute($request->getUri()->getPath())) {
            // Just move on to next middleware
            $response = $handler->handle($request);
            return $response;
        }
        // Gets token -> decodes -> finds user
        $token      = $this->getBearerToken($request->getHeaderLine('Authorization'));
        $decoded    = $this->tokenService->decodeSessionJwt($token);
        $user       = $this->userRepository->findOneBy(['id' => $decoded->sub]);

        try {
            // Apply user access control
            $this->userAuthorizationService->applyAccessControl($user);
            // Attach user to request for later use - might be unneseary if using redis
            $request = $request->withAttribute('active_user', $user);
            // Invoke the next middleware
            $response = $handler->handle($request);
            return $response;
        }
        catch (AuthException $e) {
            throw new AuthException('AUTHENTICATION_FAILED', $e->getMessage());
        }
    }
}
