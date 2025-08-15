<?php
namespace App\Middleware;

use App\Exception\AuthException;
use App\Permission\UserPermission;
use App\Repository\UserRepository;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Routing\RouteContext;

class AuthorizationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly UserPermission $userPermission
    ) {}

    public function process(Request $request, RequestHandler $handler): Response
    {
        // Route leads us to the controller and its method
        $route      = RouteContext::fromRequest($request);
        $route      = $route->getRoute();
        $callable   = $route->getCallable();
        $arguments  = $route->getArguments();
        // Store users involved for authorization
        $activeUser = $request->getAttribute('active_user');
        $targetUser = null; // Target user might not be used, depending on the action
        // Authorization for UserController methods
        // Most likely to change when new controllers are implemented
        // I must check if its an array as well, since not all routes MUST be a controller method
        if (is_array($callable) && $callable[0] == "App\Controller\UserController") {
            // If any user querying is involved in the action, these filters will
            // be applied in the Controller
            $forcedFilters = $this->userPermission->getForcedFilters($activeUser);
            $request = $request->withAttribute('forced_filters', $forcedFilters);
            // Checks if there's a target user and if valid, stores it in the request
            if (key_exists('id', $arguments)) {
                $options = ['id' => $arguments['id']];
                $options = array_merge($options, $request->getAttribute('forced_filters'));
                $targetUser = $this->userRepository->findOneByFilters($options);
                if (!$targetUser) {
                    throw new AuthException('NOT_FOUND', 'Target user not found during authorization process');
                }
                if (!$this->userPermission->canUserManageUser($activeUser, $targetUser)) {
                    throw new AuthException('WRONG_USER', 'Not allowed to manage that user');
                }
                $request = $request->withAttribute('target_user', $targetUser);
            }
            // Checks business rules for action authorization
            if (!$this->userPermission->canUserCallMethod($callable[1], $activeUser, $targetUser)) {
                throw new AuthException('WRONG_METHOD', 'Not allowed to use that controller method');
            }
        }

        // Invoke the next middleware and get response
        $response = $handler->handle($request);
        return $response;
    }
}
