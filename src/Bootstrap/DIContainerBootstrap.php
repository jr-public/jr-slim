<?php
namespace App\Bootstrap;
use App\Entity\Client;
use App\Entity\User;
use App\Repository\ClientRepository;
use App\Repository\UserRepository;
use App\Bootstrap\DoctrineBootstrap;
use DI\Container;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Doctrine\ORM\EntityManagerInterface;

class DIContainerBootstrap {
    public static function create(): Container {
        $builder = new ContainerBuilder();
        $builder->addDefinitions([
            // ENTITY MANAGER
            EntityManagerInterface::class => \DI\factory(function () {
                return DoctrineBootstrap::create();
            }),
            // RESPONSE FACTORY
            ResponseFactoryInterface::class => \DI\autowire(ResponseFactory::class),
            // REPOSITORIES
            ClientRepository::class => \DI\factory(function (EntityManagerInterface $em) {
                return $em->getRepository(Client::class);
            }),
            UserRepository::class => \DI\factory(function (EntityManagerInterface $em) {
                return $em->getRepository(User::class);
            }),
        ]);
        return $builder->build();
    }
}
