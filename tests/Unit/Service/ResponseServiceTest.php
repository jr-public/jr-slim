<?php
namespace Tests\Unit\Service;

use App\Service\ResponseService;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

class ResponseServiceTest extends TestCase
{
    private ResponseService $responseService;
    private MockObject&ResponseInterface  $mockResponse;
    private MockObject&StreamInterface  $mockStream;

    protected function setUp(): void
    {
        $this->responseService = new ResponseService();
        $this->mockResponse = $this->createMock(ResponseInterface::class);
        $this->mockStream = $this->createMock(StreamInterface::class);
    }

    public function testSuccessWithData(): void
    {
        $data = ['user' => 'test'];
        
        $this->mockResponse->expects($this->once())
            ->method('getBody')
            ->willReturn($this->mockStream);
            
        $this->mockStream->expects($this->once())
            ->method('write')
            ->with($this->callback(function($json) use ($data) {
                $decoded = json_decode($json, true);
                return $decoded['success'] === true && 
                       $decoded['data'] === $data && 
                       $decoded['error'] === null;
            }));
            
        $this->mockResponse->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($this->mockResponse);

        $result = $this->responseService->success($this->mockResponse, $data);
        
        $this->assertSame($this->mockResponse, $result);
    }

    public function testSuccessWithoutData(): void
    {
        $this->mockResponse->expects($this->once())
            ->method('getBody')
            ->willReturn($this->mockStream);
            
        $this->mockStream->expects($this->once())
            ->method('write')
            ->with($this->callback(function($json) {
                $decoded = json_decode($json, true);
                return $decoded['success'] === true && 
                       $decoded['data'] === null && 
                       $decoded['error'] === null;
            }));
            
        $this->mockResponse->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($this->mockResponse);

        $result = $this->responseService->success($this->mockResponse);
        
        $this->assertSame($this->mockResponse, $result);
    }

    public function testError(): void
    {
        $exception = new \Exception('Test error', 500);
        
        $this->mockResponse->expects($this->once())
            ->method('getBody')
            ->willReturn($this->mockStream);
            
        $this->mockStream->expects($this->once())
            ->method('write')
            ->with($this->callback(function($json) use ($exception) {
                $decoded = json_decode($json, true);
                return $decoded['success'] === false && 
                       $decoded['data'] === null &&
                       gettype($decoded['error']) === 'array';
            }));
            
        $this->mockResponse->expects($this->once())
            ->method('withHeader')
            ->with('Content-Type', 'application/json')
            ->willReturn($this->mockResponse);

        $result = $this->responseService->error($this->mockResponse, $exception);
        
        $this->assertSame($this->mockResponse, $result);
    }
}