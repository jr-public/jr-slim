<?php
namespace Tests\Unit\Service;

use App\Service\TokenService;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\TestCase;

class TokenServiceTest extends TestCase
{
    private TokenService $tokenService;
    private string $secret = 'a_very_secret_key_for_testing';
    private string $algorithm = 'HS256';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenService = new TokenService($this->secret, $this->algorithm);
    }

    public function testDefaultExpirationIsOneHour()
    {
        $payload = ['user_id' => 123];
        $token = $this->tokenService->create($payload);
        $decoded = (array) JWT::decode($token, new Key($this->secret, $this->algorithm));

        $this->assertEquals(123, $decoded['user_id']);
        $this->assertArrayHasKey('iat', $decoded);
        $this->assertArrayHasKey('exp', $decoded);
        $this->assertEquals(3600, $decoded['exp'] - $decoded['iat'], '', 1); // 1 sec leeway
    }

    public function testCustomExpirationIsRespected()
    {
        $payload = ['user_id' => 456];
        $token = $this->tokenService->create($payload, 10);
        $decoded = (array) JWT::decode($token, new Key($this->secret, $this->algorithm));
        $this->assertEquals(600, $decoded['exp'] - $decoded['iat'], '', 1);
    }

}