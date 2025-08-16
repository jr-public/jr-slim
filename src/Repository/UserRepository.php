<?php
namespace App\Repository;

use App\Entity\User;
use Doctrine\ORM\EntityRepository;

class UserRepository extends EntityRepository {
    public function findAllAsArray(): array {
        $dql = 'SELECT u FROM App\Entity\User u';
        return $this->getEntityManager()->createQuery($dql)
            ->getArrayResult();
    }
    public function findOneByFilters(array $options = []): ?User
    {
        $options['limit'] = 1;
        $result = $this->findByFilters($options, false);
        $user = $result[0] ?? null;
        return $user;
    }
    public function findByFilters(array $options = [], bool $asArray = true): array
    {
        $qb = $this->createQueryBuilder('u'); // 'u' is the alias for the User entity

        if (isset($options['id'])) {
            $qb->andWhere('u.id = :id')
                ->setParameter('id', (int) $options['id']);
        }
        if (isset($options['email'])) {
            $qb->andWhere('u.email = :email')
                ->setParameter('email', (string) $options['email']);
        }
        if (isset($options['role'])) {
            $qb->andWhere('u.role = :role')
                ->setParameter('role', (string) $options['role']);
        }
        if (isset($options['limit'])) {
            $qb->setMaxResults((int) $options['limit']);
        }
        if (isset($options['offset'])) {
            $qb->setFirstResult((int) $options['offset']);
        }
        if (isset($options['order_by'])) {
            $direction = isset($options['order_direction']) && strtoupper($options['order_direction']) === 'DESC' ? 'DESC' : 'ASC';
            $qb->orderBy('u.' . $options['order_by'], $direction);
        } else {
            $qb->orderBy('u.created', 'DESC');
        }

        try {
            $result = ($asArray) ? $qb->getQuery()->getArrayResult() : $qb->getQuery()->getResult();
            return $result;
        } catch (\Exception $e) {
            // Log the error or throw a more specific exception
            // In a real application, you'd want proper error handling.
            throw new \RuntimeException('Failed to fetch users: ' . $e->getMessage());
        }
    }

    // You might also implement a method to get the total count for pagination

}