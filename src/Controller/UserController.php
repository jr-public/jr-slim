<?php
namespace App\Controller;

use App\Controller\EntityController;
use App\Repository\UserRepository;
use App\Service\ResponseService;
use App\Service\UserService;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class UserController extends EntityController {
    private readonly UserService $userService;
    private readonly ResponseService $responseService;
    private readonly UserRepository $userRepo;
	public function __construct(UserRepository $userRepo, ResponseService $responseService, UserService $userService) {
        parent::__construct($userRepo, $responseService);
        $this->responseService = $responseService;
        $this->userService = $userService;
        $this->userRepo = $userRepo;
    }
    public function create(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        $data['client'] = $request->getAttribute('active_client');
        $user = $this->userService->create($data);
        return $this->responseService->success($response, $user->toArray());
    }
    public function patch(Request $request, Response $response, int $id): Response {
        $data = $request->getParsedBody();
        $user = $this->userRepo->findOneBy(['id' => $id]);
        $user = $this->userService->patch($user, $data['property'], $data['value']);
        return $this->responseService->success($response, $user->toArray());
    }
    public function delete(Request $request, Response $response, int $id): Response {
        $user = $this->userRepo->findOneBy(['id' => $id]);
        $user = $this->userService->delete($user);
        return $this->responseService->success($response);
    }
}