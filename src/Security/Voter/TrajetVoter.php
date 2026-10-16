<?php

namespace App\Security\Voter;

use App\Entity\Membre;
use App\Entity\Trajet;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Seul le conducteur peut modifier, annuler ou gérer les participations
 * de son trajet.
 */
final class TrajetVoter extends Voter
{
    public const EDITER = 'TRAJET_EDITER';
    public const ANNULER = 'TRAJET_ANNULER';
    public const GERER_PARTICIPATIONS = 'TRAJET_GERER_PARTICIPATIONS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDITER, self::ANNULER, self::GERER_PARTICIPATIONS], true)
            && $subject instanceof Trajet;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $membre = $token->getUser();
        if (!$membre instanceof Membre) {
            return false;
        }

        \assert($subject instanceof Trajet);

        return $subject->getConducteur() !== null
            && $subject->getConducteur()->getId() === $membre->getId();
    }
}
