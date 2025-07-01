<?php
namespace App\Bootstrap;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

class DoctrineBootstrap {
    public static function create(): EntityManager {
        $config = ORMSetup::createAttributeMetadataConfiguration(
            paths: [__DIR__ . '/../Entity'],
    		isDevMode: true,
        );

        // Create connection
        $connectionParams = [
            'driver'    => 'pdo_pgsql',
            'user'      => getenv('POSTGRES_USER'),
            'password'  => getenv('POSTGRES_PASSWORD'),
            'host'      => getenv('POSTGRES_HOST'),
            'port'      => getenv('POSTGRES_PORT'),
            'dbname'    => getenv('POSTGRES_DB')
        ];

        try {
            $connection = DriverManager::getConnection($connectionParams, $config);
            return new EntityManager($connection, $config);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to create EntityManager: ' . $e->getMessage(), 0, $e);
        }
    }
}