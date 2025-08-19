<?php
namespace App\Service;

use App\Entity\Token;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

use App\Exception\AuthException;
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

    public function createSessionJwt(User $user, int $expirationMinutes = 60): string
    {
        $now        = new \DateTimeImmutable();
        $expiration = $now->modify("+$expirationMinutes minutes");
        $jwtPayload = [
            'iat' => $now->getTimestamp(),
            'exp' => $expiration->getTimestamp(),
            'sub' => $user->get('id'),
            'type' => 'session'
        ];
        $token = JWT::encode($jwtPayload, $this->secret, $this->algorithm);
        return $token;
    }
    public function decodeSessionJwt(string $token): object
    {
        try {
            $decoded = JWT::decode($token, new Key($this->secret, $this->algorithm));
        }
        catch (BeforeValidException|SignatureInvalidException $e) {
            throw new AuthException('TOKEN_INVALID', 'TOKEN_SIGNATURE');
        }
        catch (\UnexpectedValueException|\DomainException|\InvalidArgumentException $e) {
            throw new AuthException('TOKEN_INVALID', 'TOKEN_INVALID_FORMAT');
        }
        catch (ExpiredException $e) {
            throw new AuthException('TOKEN_INVALID', 'TOKEN_EXPIRED');
        }
        if (!isset($decoded->type)) {
            throw new AuthException('TOKEN_INVALID', 'TOKEN_TYPE_REQUIRED');
        }
        if ($decoded->type != 'session') {
            throw new AuthException('TOKEN_INVALID', 'TOKEN_TYPE_MISMATCH');
        }
        if (!isset($decoded->sub)) {
            throw new AuthException('TOKEN_INVALID', 'TOKEN_SUBJECT_REQUIRED');
        }
        return $decoded;
    }
    public function createToken(string $type, User $user, int $expirationMinutes = 30): string
    {
        $now        = new \DateTimeImmutable();
        $expiration = $now->modify("+$expirationMinutes minutes");
        
        $id     = $this->random(16, 'hex');      // 128-bit ID as hex
        $secret = $this->random(32, 'urlsafe'); // 256-bit secret, URL-safe
        $hash   = password_hash($secret, PASSWORD_DEFAULT);
        $token  = $id.'.'.$secret;
        
        $tokenEntity    = new Token($id, $user, $type, $hash, $expiration);
        $this->entityManager->persist($tokenEntity);
        $this->entityManager->flush();
        return $token;
    }
    public function verifyToken(string $fullToken, string $type): User
    {
        // Split "id.secret"
        if (strpos($fullToken, '.') === false) {
            throw new BusinessException('TOKEN_INVALID', 'TOKEN_FORMAT');
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
            throw new BusinessException('TOKEN_INVALID', 'TOKEN_NOT_FOUND');
        }

        // Verify secret
        if (!password_verify($secret, $tokenEntity->get('token_hash'))) {
            throw new BusinessException('TOKEN_INVALID', 'TOKEN_SECRET_MISMATCH');
        }
        // Mark as used
        $tokenEntity->markUsed();
        $this->entityManager->flush();

        return $tokenEntity->get('user');
    }
    /**
     * Generate cryptographically secure random data
     * 
     * @param int $length Number of random bytes to generate
     * @param string $encoding Output encoding: 'hex', 'base64', 'urlsafe', or 'raw'
     * @return string
     * @throws \Exception if random_bytes fails
     */
    public function random(int $length = 32, string $encoding = 'urlsafe'): string
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('Length must be at least 1');
        }

        $bytes = random_bytes($length);
        
        return match($encoding) {
            'hex'     => bin2hex($bytes),
            'base64'  => base64_encode($bytes),
            'urlsafe' => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '='),
            'raw'     => $bytes,
            default   => throw new \InvalidArgumentException("Invalid encoding: $encoding")
        };
    }
}
