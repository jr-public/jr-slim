<?php
namespace App\Controller;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ClientController {
    // public function __construct() {}
    public function index(Request $request, Response $response): Response {
        $response->getBody()->write("<h1>Client LIST</h1>");
        return $response;
    }
    public function get(Request $request, Response $response, int $id): Response {
        $response->getBody()->write("<h1>Client $id</h1>");
        return $response;
    }
    public function create(Request $request, Response $response): Response {
        $response->getBody()->write("<h1>Client CREATE</h1>");
        return $response;
    }
    // public function update(Request $request, Response $response): Response {
    //     $response->getBody()->write("<h1>Client UPDATE</h1>");
    //     return $response;
    // }
    public function delete(Request $request, Response $response): Response {
        $response->getBody()->write("<h1>Client delete</h1>");
        return $response;
    }
    public function patch(Request $request, Response $response): Response {
        $response->getBody()->write("<h1>Client patch</h1>");
        return $response;
    }

}