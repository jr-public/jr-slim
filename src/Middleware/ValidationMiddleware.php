<?php

namespace App\Middleware;

use App\DTO\DataTransferObjectInterface;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationMiddleware
{
    private ValidatorInterface $validator;
    private DataTransferObjectInterface $dto;
    // private array $validationGroups;

    public function __construct(
        ValidatorInterface $validator,
        DataTransferObjectInterface $dto,
    ) {
        $this->validator = $validator;
        $this->dto = $dto;
    }

	function formatViolations($violations): array
	{
		$errors = [];

		foreach ($violations as $violation) {
			$errors[] = [
				'property' => $violation->getPropertyPath(),
				'message'  => $violation->getMessage(),
			];
		}

		return $errors;
	}
    public function __invoke(Request $request, RequestHandler $handler): Response
    {
        $data = $request->getParsedBody();
		if (!empty($data)) foreach ($data as $key => $value) {
			if (property_exists($this->dto, $key)) {
				$this->dto->$key = $value;
			}
		}
        $violations = $this->validator->validate($this->dto, null);
        if (count($violations) > 0) {
            throw new \Exception(json_encode($this->formatViolations($violations)));
        }
        $request = $request->withAttribute('dto', $this->dto);
        return $handler->handle($request);
    }
}