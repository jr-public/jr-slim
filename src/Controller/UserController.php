<?php
namespace App\Controller;

use App\Service\ResponseService;
use App\Service\UserService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController {
	public function __construct(
        private readonly UserService $userService, 
        private readonly ResponseService $responseService
    ) {}
    public function index(Request $request, Response $response): Response
    {
        $options = $request->getAttribute('dto')->toArray();
        $options = array_merge($options, $request->getAttribute('forced_filters'));
        $users = $this->userService->list($options);
        return $this->responseService->success($response, $users);
    }
    public function get(Request $request, Response $response, int $id): Response
    {
        $user = $request->getAttribute('target_user');
        return $this->responseService->success($response, $user->toArray());
    }
    public function create(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $data['client'] = $request->getAttribute('active_client');
        $user = $this->userService->create($data);
        return $this->responseService->success($response, $user->toArray());
    }
    public function patch(Request $request, Response $response, int $id): Response
    {
        $data = $request->getParsedBody();
        $user = $request->getAttribute('target_user');
        $user = $this->userService->patch($user, $data['property'], $data['value']);
        return $this->responseService->success($response, $user->toArray());
    }
    public function delete(Request $request, Response $response, int $id): Response
    {
        $user = $request->getAttribute('target_user');
        $user = $this->userService->delete($user);
        return $this->responseService->success($response);
    }
}