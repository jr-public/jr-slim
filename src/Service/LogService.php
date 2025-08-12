<?php
namespace App\Service;

use Monolog\Level;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Psr\Http\Message\ServerRequestInterface as Request;
class LogService
{
	private array $channels = [];
	public function uncaught(Request $request, \Throwable $throwable) {
		$logger = $this->getLogger('error');
		$details = [
			'message' => $throwable->getMessage(),
			'file' => $throwable->getFile(),
			'line' => $throwable->getLine(),
			'trace' => $throwable->getTraceAsString(),
		];
		$logger->error('Unhandled Exception', $details);
	}
	private function getLogger(string $channel = ''): Logger {
		$logger = $this->channels[$channel] ?? $this->setLogger($channel);
		return $logger;
	}
	private function setLogger(string $channel): Logger {
		$logger = new Logger($channel);
		
		$logger->pushHandler(new StreamHandler('/var/log/jr-slim/'.$channel.'.log', Level::Debug));
		$this->channels[$channel] = $logger;
		return $logger;
	}
}
