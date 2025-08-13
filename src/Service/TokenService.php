<?php
namespace App\Service;

use App\Entity\Token;
use App\Entity\User;

use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

use App\Exception\BusinessException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\DomainException;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class TokenService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $secret,
        private readonly string $algorithm
    ) {}

    public function create(array $payload, int $expirationMinutes = 60): string
    {
        if (!isset($payload['type'])) {
            throw new BusinessException('TOKEN_TYPE_REQUIRED');
        }
        $now        = new \DateTimeImmutable();
        $expiration = $now->modify("+$expirationMinutes minutes");
        // Session tokens are not stored in db. Other tokens are.
        if ($payload['type'] !== 'session') {
            $id     = bin2hex(random_bytes(16)); // 128-bit ID
            $secret = $this->random(32);         // 256-bit secret
            $hash   = password_hash($secret, PASSWORD_DEFAULT);
            $token  = $id.'.'.$secret;
            $userRef        = $this->entityManager->getReference(User::class, $payload['sub']);
            $tokenEntity    = new Token($id, $userRef, $payload['type'], $hash, $expiration);
            $this->entityManager->persist($tokenEntity);
            $this->entityManager->flush();
        }
        else {
            // Cretes a JWT using whatever is in the payload
            // Adds missing mandatory fields
            $jwtPayload = array_merge([
                'iat' => $now->getTimestamp(),
                'exp' => $expiration->getTimestamp()
            ], $payload);
            $token = JWT::encode($jwtPayload, $this->secret, $this->algorithm);
        }
        return $token;
    }
    function random(int $length = 32): string
    {
        $bytes = random_bytes($length);
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
    public function decode(string $token): object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
            if (!isset($decoded->type)) {
                throw new BusinessException('TOKEN_TYPE_REQUIRED');
            }
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
    public function verify(string $fullToken, string $type): ?User
    {
        // Split "id.secret"
        if (strpos($fullToken, '.') === false) {
            throw new BusinessException('TOKEN_INVALID');
        }
        [$id, $secret] = explode('.', $fullToken, 2);

        // Lookup by ID
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('t')
            ->from(Token::class, 't')
            ->where('t.id = :id')
            ->andWhere('t.type = :type')
            ->andWhere('t.expires_at > :now')
            ->andWhere('t.used = false')
            ->setMaxResults(1)
            ->setParameter('id', $id)
            ->setParameter('type', $type)
            ->setParameter('now', new \DateTimeImmutable());
        $tokenEntity = $qb->getQuery()->getOneOrNullResult();
        if (!$tokenEntity) {
            throw new BusinessException('TOKEN_INVALID');
        }

        // Verify secret
        if (!password_verify($secret, $tokenEntity->get('token_hash'))) {
            throw new BusinessException('TOKEN_INVALID');
        }
        // Mark as used
        $tokenEntity->markUsed();
        $this->entityManager->flush();

        return $tokenEntity->get('user');
    }

}
