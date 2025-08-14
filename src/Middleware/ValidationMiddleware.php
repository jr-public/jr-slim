<?php
namespace App\Middleware;

use App\DTO\DataTransferObjectInterface;
use App\Exception\ValidationException;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ValidationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ValidatorInterface $validator,
        private DataTransferObjectInterface $dto
    ) {}
    public function process(Request $request, RequestHandler $handler): Response
    {
        $query = $request->getQueryParams();
		if (!empty($query)) foreach ($query as $key => $value) {
			if (property_exists($this->dto, $key)) {
				$this->dto->$key = $value;
			}
		}
		$body = $request->getParsedBody();
		if (!empty($body)) foreach ($body as $key => $value) {
			if (property_exists($this->dto, $key)) {
				$this->dto->$key = $value;
			}
		}
        $violations = $this->validator->validate($this->dto, null);
        if (count($violations) > 0) {
            throw new ValidationException('VALIDATION_ERROR', json_encode($this->formatViolations($violations)));
        }
        $request = $request->withAttribute('dto', $this->dto);

        // Invoke the next middleware and get response
        $response = $handler->handle($request);

		//
        return $response;
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
}