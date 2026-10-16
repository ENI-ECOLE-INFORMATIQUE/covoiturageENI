<?php

namespace App\Service;

use App\Entity\Participation;
use App\Enum\StatutParticipation;
use App\Repository\AvisRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Règle métier 6 : un avis n'est possible qu'après la date de départ + 1 h,
 * et une seule fois par participation (acceptée).
 */
final class EligibiliteAvis
{
    public function __construct(
        private readonly AvisRepository $avisRepository,
        #[Autowire('%app.delai_avis_heures%')]
        private readonly int $delaiAvisHeures,
    ) {
    }

    public function delaiDepasse(Participation $participation): bool
    {
        $depart = $participation->getTrajet()?->getDateDepart();
        if (null === $depart) {
            return false;
        }

        return new \DateTimeImmutable() >= $depart->modify(\sprintf('+%d hours', $this->delaiAvisHeures));
    }

    public function peutEtreNotee(Participation $participation): bool
    {
        if (StatutParticipation::Accepte !== $participation->getStatut()) {
            return false;
        }

        return $this->delaiDepasse($participation);
    }

    public function peutLaisserAvis(Participation $participation): bool
    {
        return $this->peutEtreNotee($participation)
            && null === $this->avisRepository->findOneBy(['participation' => $participation]);
    }
}
