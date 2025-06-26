<?php
namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\ResponseService;
use App\Service\TokenService;
use App\Service\UserService;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController {
    private readonly ResponseService $responseService;
    private readonly UserRepository $userRepo;
    private readonly TokenService $tokenService;
    private readonly UserService $userService;
    public function __construct(UserService $userService, ResponseService $responseService, UserRepository $userRepo, TokenService $tokenService) {
        $this->userService = $userService;
        $this->responseService = $responseService;
        $this->userRepo = $userRepo;
        $this->tokenService = $tokenService;
    }
    public function register(Request $request, Response $response): Response {
        $data = $request->getParsedBody();
        $data['client'] = $request->getAttribute('active_client');
        $user = $this->userService->create($data);
        return $this->responseService->success($response, $user->toArray());
    }
    public function login(Request $request, Response $response): Response
    {
        $data   = $request->getParsedBody();
        $client = $request->getAttribute('active_client');
        $user   = $this->userRepo->findByUsernameAndClient($data['username'], $client->get('id'));
        if (!$user) {
            throw new \Exception('BAD_CREDENTIALS, Invalid username');
        } elseif (!password_verify($data['password'], $user->get('password'))) { // 
            throw new \Exception('BAD_CREDENTIALS, Invalid password'.json_encode([$data['password'], $user->get('password')]));
        }
        $token = $this->tokenService->create([
            'sub'       => $user->get('id'),
            'client_id' => $client->get('id'),
            'type'      => 'session'
        ]);
        return $this->responseService->success($response, ['token' => $token, 'user' => $user->toArray()]);
    }
}