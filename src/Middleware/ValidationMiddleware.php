<?php

namespace App\Middleware;

use App\DTO\DataTransferObjectInterface;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationMiddleware implements MiddlewareInterface
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
    public function process(Request $request, RequestHandler $handler): Response
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

        // Invoke the next middleware and get response
        $response = $handler->handle($request);

		//
        return $response;
    }
}