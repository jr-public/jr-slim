<?php
namespace App\Bootstrap;

use App\Controller\AuthController;
use App\Controller\UserController;
use App\DTO\UserCreateDTO;
use App\DTO\UserPatchDTO;
use App\DTO\QueryBuilderDTO;
use App\Middleware\AuthenticationMiddleware;
use App\Middleware\AuthorizationMiddleware;
use App\Middleware\ClientMiddleware;
use App\Service\ResponseService;

use DI\Container;
use DI\Bridge\Slim\Bridge;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use Psr\Http\Message\ServerRequestInterface as Request;

class SlimBootstrap
{
    public static function createApp(Container $container): App
    {
        $app = Bridge::create($container);
        self::registerMiddleware($app);
        self::registerRoutes($app);
        return $app;
    }

    protected static function registerMiddleware(App $app): void
    {
        $app->addBodyParsingMiddleware();
        $app->add(AuthorizationMiddleware::class);
        $app->add(AuthenticationMiddleware::class);
        $app->addRoutingMiddleware();
        $app->add(ClientMiddleware::class);
        $errorMiddleware = $app->addErrorMiddleware(true, true, true);
        $errorMiddleware->setDefaultErrorHandler(self::getCustomErrorHandler($app)); 
    }

    protected static function registerRoutes(App $app): void
    {
        $validationMiddlewareFactory = $app->getContainer()->get('ValidationMiddlewareFactory');

        $app->group('/users', function (RouteCollectorProxy $group) use ($validationMiddlewareFactory) {
            // INDEX
            foreach (['', '/', '/index'] as $path) {
                $group->get($path, [UserController::class, 'index'])
                    ->add($validationMiddlewareFactory(QueryBuilderDTO::class));
            }
            // CREATE
            foreach (['', '/', '/create'] as $path) {
                $group->post($path, [UserController::class, 'create'])
                    ->add($validationMiddlewareFactory(UserCreateDTO::class));
            }
            // PATCH
            $group->patch('/{id}', [UserController::class, 'patch'])
                ->add($validationMiddlewareFactory(UserPatchDTO::class));
            // GET and DELETE dont need extra validation
            $group->get('/{id}', [UserController::class, 'get']);   // GET /users/{id}: Get a single user by ID
            $group->delete('/{id}', [UserController::class, 'delete']); // DELETE /users/{id}: Delete a user by ID
        });
        $app->group('/clients', function (RouteCollectorProxy $group) {
            // $group->get('/', [ClientController::class, 'index']);      // GET /clients: Get all users
            // $group->post('/', [ClientController::class, 'store']);     // POST /clients: Create a new user
            // $group->get('/{id}', [ClientController::class, 'get']);   // GET /clients/{id}: Get a single user by ID
            // $group->put('/{id}', [ClientController::class, 'update']); // PUT /clients/{id}: Update a user by ID
            // $group->delete('/{id}', [ClientController::class, 'delete']); // DELETE /clients/{id}: Delete a user by ID
            // $group->patch('/{id}', [ClientController::class, 'patch']);   // PATCH /clients/{id}: Partially update a user by ID
        });
        $app->group('/guest', function (RouteCollectorProxy $group) use ($validationMiddlewareFactory) {
            $group->post('/login', [AuthController::class, 'login']);
            $group->post('/register', [AuthController::class, 'register'])
                ->add($validationMiddlewareFactory(UserCreateDTO::class));
            // $group->get('/profile', [UserController::class, 'profile']);
            // $group->get('/forgot-password', [UserController::class, 'forgotPassword']);
            // $group->post('/reset-password', [UserController::class, 'resetPassword']);
            // $group->get('/verify-email', [UserController::class, 'verifyEmail']);
            // $group->get('/resend-verification', [UserController::class, 'resendVerification']);
            // $group->get('/change-password', [UserController::class, 'changePassword']);
            // check username
            // check email
        });
    }
    protected static function getCustomErrorHandler(App $app): callable {
        $customErrorHandler = function (
            Request $request, // Must be the first parameter
            \Throwable $exception, // Must be the second parameter
            bool $displayErrorDetails, // Comes from errorMiddleware config
            bool $logErrors, // Comes from errorMiddleware config
            bool $logErrorDetails // Comes from errorMiddleware config
        ) use ($app) { // Use $app to access the container or response factory
            $responseService = $app->getContainer()->get(ResponseService::class);
            $responseFactory = $app->getResponseFactory();
            $response = $responseFactory->createResponse();
            return $responseService->error($response, $exception); // The error method receives only the Throwable
        };

        return $customErrorHandler;
    }
}   
