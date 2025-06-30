<?php
namespace App\Bootstrap;

use App\Controller\AuthController;
use App\Controller\UserController;
use App\Middleware\AuthenticationMiddleware;
use App\Middleware\AuthorizationMiddleware;
use App\Middleware\ClientMiddleware;
use DI\Container;
use DI\Bridge\Slim\Bridge;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

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
        $app->addErrorMiddleware(true, true, true);
    }

    protected static function registerRoutes(App $app): void
    {
        $app->group('/users', function (RouteCollectorProxy $group) {
            $group->get('', [UserController::class, 'index']);      // GET /users: Get all users
            $group->get('/', [UserController::class, 'index']);      // GET /users: Get all users
            $group->get('/index', [UserController::class, 'index']);      // GET /users: Get all users
            $group->post('', [UserController::class, 'create']);     // POST /users: Create a new user
            $group->post('/', [UserController::class, 'create']);     // POST /users: Create a new user
            $group->post('/create', [UserController::class, 'create']);     // POST /users: Create a new user
            $group->get('/{id}', [UserController::class, 'get']);   // GET /users/{id}: Get a single user by ID
            $group->delete('/{id}', [UserController::class, 'delete']); // DELETE /users/{id}: Delete a user by ID
            $group->patch('/{id}', [UserController::class, 'patch']);   // PATCH /users/{id}: Partially update a user by ID
        });
        $app->group('/clients', function (RouteCollectorProxy $group) {
            // $group->get('/', [ClientController::class, 'index']);      // GET /clients: Get all users
            // $group->post('/', [ClientController::class, 'store']);     // POST /clients: Create a new user
            // $group->get('/{id}', [ClientController::class, 'get']);   // GET /clients/{id}: Get a single user by ID
            // $group->put('/{id}', [ClientController::class, 'update']); // PUT /clients/{id}: Update a user by ID
            // $group->delete('/{id}', [ClientController::class, 'delete']); // DELETE /clients/{id}: Delete a user by ID
            // $group->patch('/{id}', [ClientController::class, 'patch']);   // PATCH /clients/{id}: Partially update a user by ID
        });
        $app->group('/guest', function (RouteCollectorProxy $group) {
            $group->post('/login', [AuthController::class, 'login']);
            $group->post('/register', [AuthController::class, 'register']);
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
}   
