<?php

namespace App\Repository;

use App\Entity\Site;
use App\Entity\Trajet;
use App\Enum\SensTrajet;
use App\Enum\StatutTrajet;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trajet>
 */
class TrajetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trajet::class);
    }

    /**
     * Recherche les trajets ouverts à venir.
     *
     * Le filtre « places libres » est appliqué par l'appelant via
     * Trajet::getNbPlacesRestantes() : la capacité acceptée est une
     * donnée métier, pas une simple colonne.
     *
     * @return Trajet[]
     */
    public function rechercher(
        ?Site $site = null,
        ?SensTrajet $sens = null,
        ?\DateTimeImmutable $date = null,
        ?string $ville = null,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->andWhere('t.statut = :statut')
            ->andWhere('t.dateDepart >= :maintenant')
            ->setParameter('statut', StatutTrajet::Ouvert)
            ->setParameter('maintenant', new \DateTimeImmutable())
            ->orderBy('t.dateDepart', 'ASC');

        if (null !== $site) {
            $qb->andWhere('t.site = :site')
                ->setParameter('site', $site);
        }

        if (null !== $sens) {
            $qb->andWhere('t.sens = :sens')
                ->setParameter('sens', $sens);
        }

        if (null !== $date) {
            $qb->andWhere('t.dateDepart >= :debutJournee')
                ->andWhere('t.dateDepart < :finJournee')
                ->setParameter('debutJournee', $date->setTime(0, 0))
                ->setParameter('finJournee', $date->setTime(0, 0)->modify('+1 day'));
        }

        if (null !== $ville && '' !== trim($ville)) {
            $qb->andWhere('LOWER(t.villeDepart) LIKE :ville OR LOWER(t.villeArrivee) LIKE :ville')
                ->setParameter('ville', '%'.mb_strtolower(trim($ville)).'%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trajets d'un conducteur, plus récents en premier.
     *
     * @return Trajet[]
     */
    public function findByConducteur(int $membreId): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.conducteur', 'c')
            ->andWhere('c.id = :membreId')
            ->setParameter('membreId', $membreId)
            ->orderBy('t.dateDepart', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Prochains trajets ouverts publiés par un conducteur (pour son profil public).
     *
     * @return Trajet[]
     */
    public function findFutursOuvertsPour(\App\Entity\Membre $conducteur): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.conducteur = :conducteur')
            ->andWhere('t.statut = :statut')
            ->andWhere('t.dateDepart >= :maintenant')
            ->setParameter('conducteur', $conducteur)
            ->setParameter('statut', StatutTrajet::Ouvert)
            ->setParameter('maintenant', new \DateTimeImmutable())
            ->orderBy('t.dateDepart', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre de trajets ouverts à venir, groupés par campus (statistiques admin).
     *
     * @return array<int, array{nom: string, nb: int}>
     */
    public function compterOuvertsFutursParSite(): array
    {
        return $this->createQueryBuilder('t')
            ->select('s.nom AS nom, COUNT(t.id) AS nb')
            ->join('t.site', 's')
            ->andWhere('t.statut = :statut')
            ->andWhere('t.dateDepart >= :maintenant')
            ->setParameter('statut', StatutTrajet::Ouvert)
            ->setParameter('maintenant', new \DateTimeImmutable())
            ->groupBy('s.id')
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
