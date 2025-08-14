<?php
namespace App\Repository;

use App\Entity\Client;
use Doctrine\ORM\EntityRepository;

class ClientRepository extends EntityRepository
{
    public function seed(): void
    {
        // Check if localhost client already exists
        $existingClient = $this->findOneBy(['domain' => 'localhost']);
        if ($existingClient) {
            return; // Already seeded
        }

        // Create default localhost client
        $client = new Client();
        $client->init([
            'name' => 'jr-slim',
            'domain' => 'localhost'
        ]);
        $this->getEntityManager()->persist($client);
        $this->getEntityManager()->flush();
    }

    public function ensureDefaultClient(): Client
    {
        $client = $this->findOneBy(['domain' => 'localhost']);
        
        if (!$client) {
            $this->seed();
            $client = $this->findOneBy(['domain' => 'localhost']);
        }

        return $client;
    }
}