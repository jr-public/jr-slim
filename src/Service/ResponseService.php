<?php
namespace App\Service;

use App\Exception\ApiException;

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
            'error'     => [
                'timestamp' => date('c'),
                'message'   => ($exception instanceof ApiException)?$exception->getDetail():$exception->getMessage(),
                'method'    => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
                'uri'       => $_SERVER['REQUEST_URI'] ?? 'unknown',
                'class'     => get_class($exception),
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
                'trace'     => $exception->getTrace(),
            ]
        ];
        
        $json = json_encode($_response);
        $response->getBody()->write($json);
        return $response->withHeader('Content-Type', 'application/json');
    }
}
