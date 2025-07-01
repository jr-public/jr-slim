<?php
namespace App\Service;

use Psr\Http\Message\ResponseInterface as Response;

class ResponseService
{
    public function success(Response $response, ?array $data = null): Response
    {
        $_response = [
            'success'   => true,
            'data'      => $data,
            'error'     => null
        ];
        $json = json_encode($_response);
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }
    public function error(Response $response, \Throwable $exception): Response
    {
        $_response = [
            'success'   => false,
            'data'      => null,
        ];
        $_response['error'] = [
            'message'   => $exception->getMessage(),
            'class'     => get_class($exception),
            'file'      => $exception->getFile(),
            'line'      => $exception->getLine(),
            'trace'     => $exception->getTrace(),
            'method'    => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'uri'       => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'timestamp' => date('c')
        ];
        $json = json_encode($_response);
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
