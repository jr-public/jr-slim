<?php
namespace App\Middleware;


use App\Exception\AuthException;
use App\Repository\UserRepository;
use App\Service\TokenService;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AuthenticationMiddleware implements MiddlewareInterface
{
    private readonly UserRepository $user_repo;
	private readonly TokenService $token_s;
    public function __construct(UserRepository $user_repo, TokenService $token_s)
    {
        $this->user_repo = $user_repo;
        $this->token_s = $token_s;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        // 
        $client = $request->getAttribute('active_client');

        //
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader) || !str_starts_with($authHeader, 'Bearer ')) {
            throw new AuthException('BAD_TOKEN', 'Authorization header missing or malformed');
        }
        $token = substr($authHeader, 7);
        $decoded = $this->token_s->decode($token);
        if (!isset($decoded->sub)) {
            throw new AuthException('BAD_TOKEN', 'Invalid token: missing user identifier');
        }
        if (!isset($decoded->client_id) || $decoded->client_id !== $client->get('id')) {
            throw new AuthException('BAD_TOKEN', 'Invalid token: client mismatch');
        }
        if (!isset($decoded->type) || $decoded->type !== 'session') {
            throw new AuthException('BAD_TOKEN', 'Invalid token: invalid token type');
        }
        
        $user = $this->user_repo->get($decoded->sub, $client->get('id'));
        if (!$user) {
            throw new AuthException('BAD_TOKEN', 'Invalid token - user not found');
        }
        if ($user->get('status') !== 'active') {
            throw new AuthException('NOT_ACTIVE', 'Account is not active');
        }
        if ($user->get('reset_password')) {
            throw new AuthException('RESET_PASSWORD', 'Password reset required');
        }

		$request = $request->withAttribute('active_user', $user);

        // Invoke the next middleware and get response
        $response = $handler->handle($request);
        return $response;
    }
}
