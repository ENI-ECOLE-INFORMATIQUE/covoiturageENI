<?php

namespace App\Security\Voter;

use App\Entity\Membre;
use App\Entity\Vehicule;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Seul le propriétaire modifie ou supprime son véhicule.
 *
 * @extends Voter<string, Vehicule>
 */
final class VehiculeVoter extends Voter
{
    public const EDITER = 'VEHICULE_EDITER';
    public const SUPPRIMER = 'VEHICULE_SUPPRIMER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::EDITER, self::SUPPRIMER], true)
            && $subject instanceof Vehicule;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $membre = $token->getUser();
        if (!$membre instanceof Membre) {
            return false;
        }

        return $subject->getMembre() !== null
            && $subject->getMembre()->getId() === $membre->getId();
    }
}
