<?php
namespace App\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class TokenService
{
    public function create(array $payload, int $expirationMinutes = 60): string
    {
        $now = new \DateTimeImmutable();
        $expiration = $now->modify("+$expirationMinutes minutes");

        $jwtPayload = array_merge($payload, [
            'iat' => $now->getTimestamp(),
            'exp' => $expiration->getTimestamp()
        ]);
        return JWT::encode($jwtPayload, getenv('JWT_SECRET'), getenv('JWT_ALGO'));
    }

    public function decode(string $token): object
    {
        return JWT::decode($token, new Key(getenv('JWT_SECRET'), getenv('JWT_ALGO')));
    }
}
