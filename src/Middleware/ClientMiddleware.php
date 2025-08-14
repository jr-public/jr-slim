<?php
namespace App\Middleware;

use App\Exception\AuthException;
use App\Repository\ClientRepository;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class ClientMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ClientRepository $clientRepository
    ) {}

    public function process(Request $request, RequestHandler $handler): Response
    {
        // used to be //$request->getUri()->getHost() but i changed the approach
        // for now multi-tenancy is disabled
        $client = $this->clientRepository->ensureDefaultClient();
        if (!$client) {
            throw new AuthException('NOT_FOUND', 'Client not found. input: '.$request->getUri()->getHost());
        }
        $request = $request->withAttribute('active_client', $client);

        // Invoke the next middleware and get response
        $response = $handler->handle($request);

		//
        return $response;
    }
}
