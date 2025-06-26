<?php
namespace App\Repository;

use App\Entity\User;
use App\Entity\Client;
use Doctrine\ORM\EntityRepository;

class UserRepository extends EntityRepository {
    public function get(int $id, int $client_id): ?User {
        $dql = 'SELECT u FROM App\Entity\User u WHERE u.id = :id AND u.client = :client_id';
        $query = $this->getEntityManager()->createQuery($dql)
            ->setParameter('id', $id)
            ->setParameter('client_id', $client_id);
        $user = $query->getOneOrNullResult();
        return $user;
    }
    public function findByUsernameAndClient(string $username, int $clientId): ?User {
        $dql = 'SELECT u FROM App\Entity\User u WHERE u.username = :username AND u.client = :client_id';
        return $this->getEntityManager()->createQuery($dql)
            ->setParameter('username', $username)
            ->setParameter('client_id', $clientId)
            ->getOneOrNullResult();
    }
    public function findAllAsArray(): array {
        $dql = 'SELECT u FROM App\Entity\User u';
        return $this->getEntityManager()->createQuery($dql)
            ->getArrayResult();
    }
    // public function findActiveUsersByClient(Client $client): array
    // {
    //     return $this->createQueryBuilder('u')
    //         ->where('u.client = :client')
    //         ->andWhere('u.status = :status')
    //         ->setParameter('client', $client)
    //         ->setParameter('status', 'active')
    //         ->getQuery()
    //         ->getResult();
    // }
}