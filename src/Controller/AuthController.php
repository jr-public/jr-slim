<?php
namespace App\Controller;

use App\Service\ResponseService;
use App\Service\UserService;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController {
    private readonly ResponseService $responseService;
    private readonly UserService $userService;
    public function __construct(UserService $userService, ResponseService $responseService)
    {
        $this->userService = $userService;
        $this->responseService = $responseService;
    }
    public function register(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $data['client'] = $request->getAttribute('active_client');
        $user = $this->userService->create($data);
        return $this->responseService->success($response, $user->toArray());
    }
    public function activateAccount(Request $request, Response $response): Response
    {
        $token = $request->getAttribute('dto')->token;
        $this->userService->activateAccount($token);
        return $this->responseService->success($response);
    }
    public function login(Request $request, Response $response): Response
    {
        $data   = $request->getParsedBody();
        $client = $request->getAttribute('active_client');
        $login  = $this->userService->login($client, $data['username'], $data['password']);
        return $this->responseService->success($response, $login);
    }
    public function forgotPassword(Request $request, Response $response): Response
    {
        $data   = $request->getParsedBody();
        $this->userService->forgotPassword($data['email']);
        // We always return a success, users shouldn't know if emails are in use or not
        return $this->responseService->success($response); 
    }
    public function resetPassword(Request $request, Response $response): Response
    {
        $data = $request->getParsedBody();
        $this->userService->resetPassword($data['token'], $data['password']);
        return $this->responseService->success($response);
    }
}