<?php

namespace App\Repository;

use App\Entity\Lobby;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lobby>
 */
class LobbyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lobby::class);
    }

    /**
     * @return Lobby[]
     */
    public function findOpenLobbies(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.status = :status')
            ->setParameter('status', Lobby::STATUS_WAITING)
            ->orderBy('l.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
