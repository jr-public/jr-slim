<?php
namespace App\Middleware;

use App\Service\ContextService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class ContextMiddleware implements MiddlewareInterface
{
	private readonly ContextService $contextService;
	public function __construct(ContextService $contextService)
	{
		$this->contextService = $contextService;
	}
    public function process(Request $request, RequestHandler $handler): Response
    {
		$activeUser = $request->getAttribute('active_user');
		if ($activeUser != null) $this->contextService->setActiveUser($activeUser);
		$targetUser = $request->getAttribute('target_user');
		if ($targetUser != null) $this->contextService->setTargetUser($targetUser);
		$client = $request->getAttribute('active_client');
		if ($client != null) $this->contextService->setClient($client);
		$forcedFilters = $request->getAttribute('forced_filters');
		if ($forcedFilters != null) $this->contextService->setForcedFilters($forcedFilters);

        // Invoke the next middleware and get response
        $response = $handler->handle($request);

		//
        return $response;
    }
}
