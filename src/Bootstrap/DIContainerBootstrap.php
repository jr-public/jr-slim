<?php
namespace App\Bootstrap;

use App\Bootstrap\DoctrineBootstrap;
use App\Entity\Client;
use App\Entity\User;
use Predis\Client AS RedisClient;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\ValidationMiddleware;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use App\Service\EmailService;
use App\Service\LogService;
use App\Service\TokenService;
use DI\Container;
use DI\ContainerBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DIContainerBootstrap {
    public static function create(): Container {
        $builder = new ContainerBuilder();
        $builder->addDefinitions([
            // SERVICES
            TokenService::class => \DI\autowire()
                ->constructorParameter('secret', \DI\env('JWT_SECRET'))
                ->constructorParameter('algorithm', \DI\env('JWT_ALGO')),
            EmailService::class => \DI\factory(function () {
                return new EmailService(
                    getenv('MAIL_HOST'),
                    (int) (getenv('MAIL_PORT')),
                    getenv('MAIL_USERNAME'),
                    getenv('MAIL_PASSWORD'),
                    getenv('MAIL_FROM_EMAIL'),
                    getenv('MAIL_FROM_NAME')
                );
            }),
            EntityManagerInterface::class => \DI\factory(function () {
                return DoctrineBootstrap::create();
            }),
            ResponseFactoryInterface::class => \DI\autowire(ResponseFactory::class),
            // REPOSITORIES
            ClientRepository::class => \DI\factory(function (EntityManagerInterface $em) {
                return $em->getRepository(Client::class);
            }),
            UserRepository::class => \DI\factory(function (EntityManagerInterface $em) {
                return $em->getRepository(User::class);
            }),
            //
            ValidatorInterface::class => \DI\factory(function () {
                return Validation::createValidatorBuilder()
                    ->enableAttributeMapping()
                    ->getValidator();
            }),
            'RateLimitMiddlewareFactory' => \DI\factory(function (ContainerInterface $c) {
                return function (string $prefix, int $maxAttempts, int $windowSeconds) use ($c) {
                    return new RateLimitMiddleware(
                        $c->get(RedisClient::class),
                        $prefix,
                        $maxAttempts,
                        $windowSeconds
                    );
                };
            }),
            'ValidationMiddlewareFactory' => \DI\factory(function (ContainerInterface $c) {
                return function (string $dtoClass, array $validationGroups = []) use ($c) {
                    return new ValidationMiddleware(
                        $c->get(ValidatorInterface::class),
                        $c->get($dtoClass),
                        $validationGroups
                    );
                };
            }),
            RedisClient::class => \DI\factory(function () {
                return new RedisClient([
                    'scheme' => 'tcp',
                    'host'   => getenv('REDIS_HOST'),
                    'port'   => (int) (getenv('REDIS_PORT')),
                    'database' => (int) (getenv('REDIS_DB')),
                ]);
            })
        ]);
        return $builder->build();
    }
}