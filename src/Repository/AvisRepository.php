<?php

namespace App\Repository;

use App\Entity\Avis;
use App\Entity\Membre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Avis>
 */
class AvisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avis::class);
    }

    /**
     * Moyenne des notes reçues par un membre (null si aucun avis).
     */
    public function moyennePour(Membre $membre): ?float
    {
        $moyenne = $this->createQueryBuilder('a')
            ->select('AVG(a.note)')
            ->andWhere('a.cible = :membre')
            ->setParameter('membre', $membre->getId())
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $moyenne && '' !== $moyenne ? (float) $moyenne : null;
    }
}
