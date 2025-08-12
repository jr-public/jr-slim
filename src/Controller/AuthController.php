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
    private readonly TokenService $tokenService;
    private readonly UserService $userService;
    public function __construct(UserService $userService, ResponseService $responseService, TokenService $tokenService) {
        $this->userService = $userService;
        $this->responseService = $responseService;
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
        $user   = $this->userService->login($client, $data['username'], $data['password']);
        $token  = $this->tokenService->create([
            'sub'       => $user->get('id'),
            'client_id' => $client->get('id'),
            'type'      => 'session'
        ]);
        return $this->responseService->success($response, ['token' => $token, 'user' => $user->toArray()]);
    }
}