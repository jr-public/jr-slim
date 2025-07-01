<?php
namespace App\Service;

use App\Exception\BusinessException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\DomainException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenService
{
    public function __construct(
        private readonly string $secret,
        private readonly string $algorithm
    ) {}

    public function create(array $payload, int $expirationMinutes = 60): string
    {
        $now = new \DateTimeImmutable();
        $expiration = $now->modify("+$expirationMinutes minutes");

        $jwtPayload = array_merge($payload, [
            'iat' => $now->getTimestamp(),
            'exp' => $expiration->getTimestamp()
        ]);
        return JWT::encode($jwtPayload, $this->secret, $this->algorithm);
    }

    public function decode(string $token): object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            return $decoded;
        }
        catch (BeforeValidException|SignatureInvalidException $e) {
            throw new BusinessException('TOKEN_INVALID');
        }
        catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException $e) {
            throw new BusinessException('TOKEN_INVALID_FORMAT');
        }
        catch (ExpiredException $e) {
            throw new BusinessException('TOKEN_EXPIRED');
        }
    }
}
