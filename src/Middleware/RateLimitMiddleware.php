<?php
namespace App\Middleware;

use App\Exception\BusinessException;
use Predis\Client as RedisClient;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class RateLimitMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly RedisClient $redis,
        private readonly string $keyPrefix,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds
    ) {}

    public function process(Request $request, RequestHandler $handler): Response
    {
        $identifier = $this->getIdentifier($request);
        $key = "{$this->keyPrefix}:{$identifier}";

        $now = time();
        $windowStart = $now - $this->windowSeconds;

        // Remove expired entries
        $this->redis->zremrangebyscore($key, 0, $windowStart);
        $currentCount = $this->redis->zcard($key);

        // Check if limit exceeded
        if ($currentCount >= $this->maxAttempts) {
            $oldestRequest = $this->redis->zrange($key, 0, 0, 'WITHSCORES');
            $resetTime = !empty($oldestRequest) ? reset($oldestRequest) + $this->windowSeconds : $now + $this->windowSeconds;
            
            throw new BusinessException(
                'RATE_LIMIT_EXCEEDED', 
                "Too many attempts. Try again in " . ($resetTime - $now) . " seconds.",
                429 // HTTP 429 Too Many Requests
            );
        }

        // Add current request timestamp
        $this->redis->zadd($key, $now, $now . ':' . $this->generateRequestId());
        $this->redis->expire($key, $this->windowSeconds);

        // Process request
        $response = $handler->handle($request);

        // Add comprehensive rate limit headers
        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxAttempts)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $this->maxAttempts - $currentCount - 1))
            ->withHeader('X-RateLimit-Reset', (string) ($now + $this->windowSeconds))
            ->withHeader('X-RateLimit-Window', (string) $this->windowSeconds)
            ->withHeader('Retry-After', (string) $this->windowSeconds);
    }

    private function getIdentifier(Request $request): string
    {
        // Try to get authenticated user ID first (more specific)
        $user = $request->getAttribute('active_user');
        if ($user && method_exists($user, 'get')) { // Tal vez podría chequear que sea user entity
            return "user:{$user->get('id')}";
        }
        
        // Fall back to IP address for unauthenticated requests
        $ip = $this->getClientIp($request);
        return "ip:{$ip}";
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