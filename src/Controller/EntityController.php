<?php
namespace App\Controller;

use App\Service\ResponseService;
use Doctrine\ORM\EntityRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

abstract class EntityController {
    private readonly EntityRepository $entityRepo;
    private readonly ResponseService $responseService;
	public function __construct(EntityRepository $entityRepo, ResponseService $responseService) {
        $this->entityRepo = $entityRepo;
        $this->responseService = $responseService;
    }
    public function index(Request $request, Response $response): Response {
		$entities = $this->entityRepo->findAllAsArray();
        return $this->responseService->success($response, $entities);
    }
    public function get(Request $request, Response $response, int $id): Response {
        $entity = $this->entityRepo->findOneBy(['id' => $id]);
        return $this->responseService->success($response, $entity->toArray());
    }
    abstract public function create(Request $request, Response $response): Response;
    abstract public function patch(Request $request, Response $response, int $id): Response;
    abstract public function delete(Request $request, Response $response, int $id): Response;
    
}