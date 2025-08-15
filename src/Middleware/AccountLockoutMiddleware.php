<?php
namespace App\Middleware;

use App\Exception\AuthException;
use Predis\Client as RedisClient;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class AccountLockoutMiddleware implements MiddlewareInterface
{

    public function __construct(
        private readonly RedisClient $redis,
        private readonly int $maxAttempts = 5,
        private readonly int $lockoutWindowSeconds = 900, // 15 minutes
        private readonly int $attemptWindowSeconds = 300 // 5 minutes
    ) {}

    public function process(Request $request, RequestHandler $handler): Response
    {
        $identifier = $this->getIdentifier($request);
        $key = "lockout:lock:{$identifier}";

        // Check if account is locked
        if ($this->redis->exists($key) > 0) {
            $lockTime = $this->redis->get($key);
            $lockoutExpiry = $lockTime ? (int)$lockTime + $this->lockoutWindowSeconds : 0;
            $remainingTime = $lockoutExpiry - time();
            throw new AuthException(
                'ACCOUNT_LOCKED',
                "Account temporarily locked. Try again in {$remainingTime} seconds.",
                423 // HTTP 423 Locked
            );
        }

        // Process the request
        try {
            $response = $handler->handle($request);
        } catch (AuthException $th) {
            if ($th->getDetail() != 'BAD_PASSWORD') {
                throw $th;
            }
            $this->recordFailedAttempt($identifier);
            if ($this->shouldLockAccount($identifier)) {
                $this->lockAccount($identifier);
                throw new AuthException(
                    'ACCOUNT_LOCKED',
                    "Too many failed login attempts. Account locked for " . ($this->lockoutWindowSeconds / 60) . " minutes.",
                    423
                );
            }
            throw $th;
        }
        // Clear failed attempts on successful login
        $attemptKey = "lockout:attempts:{$identifier}";
        $this->redis->del($attemptKey);
        return $response;
    }

    private function getIdentifier(Request $request): string
    {
        $body = $request->getParsedBody();
        $username = $body['username'] ?? '';
        
        if (empty($username)) {
            // Fallback to IP if no username provided
            return "ip:" . $this->getClientIp($request);
        }
        
        return "user:" . hash('sha256', $username);
    }

    private function recordFailedAttempt(string $identifier): void
    {
        $attemptKey = "lockout:attempts:{$identifier}";
        $now = time();
        $windowStart = $now - $this->attemptWindowSeconds;

        // Remove old attempts outside the window
        $this->redis->zremrangebyscore($attemptKey, 0, $windowStart);
        
        // Add current attempt
        $this->redis->zadd($attemptKey, $now, $now . ':' . $this->generateRequestId());
        $this->redis->expire($attemptKey, $this->attemptWindowSeconds);
    }

    private function shouldLockAccount(string $identifier): bool
    {
        $attemptKey = "lockout:attempts:{$identifier}";
        $attemptCount = $this->redis->zcard($attemptKey);
        
        return $attemptCount >= $this->maxAttempts;
    }

    private function lockAccount(string $identifier): void
    {
        $lockKey = "lockout:lock:{$identifier}";
        $now = time();
        
        $this->redis->set($lockKey, $now);
        $this->redis->expire($lockKey, $this->lockoutWindowSeconds);
    }

    private function getClientIp(Request $request): string
    {
        // Check headers in order of preference
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Load balancers/proxies
            'HTTP_X_REAL_IP',            // Nginx reverse proxy
            'HTTP_X_FORWARDED',          // Proxies
            'HTTP_X_CLUSTER_CLIENT_IP',  // Cluster
            'HTTP_FORWARDED_FOR',        // Proxies
            'HTTP_FORWARDED',            // RFC 7239
            'REMOTE_ADDR'                // Standard
        ];

        foreach ($headers as $header) {
            $ip = $_SERVER[$header] ?? null;
            if ($ip) {
                // Handle comma-separated IPs (take first valid one)
                if (str_contains($ip, ',')) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                
                // Validate IP and exclude private/reserved ranges for security
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }

        // Fallback - log this as it might indicate configuration issues
        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private function generateRequestId(): string
    {
        return bin2hex(random_bytes(8));
    }
}