<?php
namespace App\Middleware;

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
        // private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository,
        private readonly UserPermission $userPermission
    ) {}

    public function process(Request $request, RequestHandler $handler): Response
    {
        $activeUser = $request->getAttribute('active_user');
        $targetUser = null;
        
        $routeContext = RouteContext::fromRequest($request);
        $route = $routeContext->getRoute();

        // CONTROLLER / METHOD PERMISSIONS
        // Assume all my callables are arrays that contain ["App\\Controller\\ControllerName","methodName"]
        $callable = $route->getCallable();
        if ($callable[0] == "App\Controller\UserController") {
            $forcedFilters = $this->userPermission->getForcedFilters($activeUser);
            $request = $request->withAttribute('forced_filters', $forcedFilters);
            
            // USER MANAGEMENT PERMISSIONS
            $route_arguments = $route->getArguments();
            if (key_exists('id', $route_arguments)) {
                $options = ['id' => $route_arguments['id']];
                $options = array_merge($options, $request->getAttribute('forced_filters'));
                $targetUser = $this->userRepository->findOneByFilters($options);
                if (!$targetUser) {
                    throw new \Exception('Target user not found during authorization process');
                }
                if (!$this->userPermission->canUserManageUser($activeUser, $targetUser)) {
                    throw new \Exception('Not allowed to manage that user');
                }
                $request = $request->withAttribute('target_user', $targetUser);
            }
            
            if (!$this->userPermission->canUserCallMethod($callable[1], $activeUser, $targetUser)) {
                throw new \Exception("Not allowed to use that controller method");
            }
        }

        // Invoke the next middleware and get response
        $response = $handler->handle($request);
        return $response;
    }
}
