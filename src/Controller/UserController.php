<?php
namespace App\Controller;

use App\Service\ResponseService;
use App\Service\UserService;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController {
    private readonly UserService $userService;
    private readonly ResponseService $responseService;
	public function __construct(UserService $userService, ResponseService $responseService)
    {
        $this->userService = $userService;
        $this->responseService = $responseService;
    }
    public function index(Request $request, Response $response): Response
    {
        $users = $this->userService->listAsArray();
        return $this->responseService->success($response, $users);
    }
    public function get(Request $request, Response $response, int $id): Response
    {
        $user = $request->getAttribute('target_user') ?? $this->userService->get($id);
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
        $user = $this->userService->get($id);
        $user = $this->userService->patch($user, $data['property'], $data['value']);
        return $this->responseService->success($response, $user->toArray());
    }
    public function delete(Request $request, Response $response, int $id): Response
    {
        $user = $this->userService->get($id);
        $user = $this->userService->delete($user);
        return $this->responseService->success($response);
    }
}