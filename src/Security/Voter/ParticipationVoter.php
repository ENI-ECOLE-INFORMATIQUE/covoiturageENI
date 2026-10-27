<?php

namespace App\Security\Voter;

use App\Entity\Membre;
use App\Entity\Participation;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Le passager annule sa propre participation ; le conducteur du trajet
 * accepte, refuse ou annule les participations.
 *
 * @extends Voter<string, Participation>
 */
final class ParticipationVoter extends Voter
{
    public const ANNULER = 'PARTICIPATION_ANNULER';
    public const GERER = 'PARTICIPATION_GERER';
    public const NOTER = 'PARTICIPATION_NOTER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::ANNULER, self::GERER, self::NOTER], true)
            && $subject instanceof Participation;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $membre = $token->getUser();
        if (!$membre instanceof Membre) {
            return false;
        }

        $passager = $subject->getMembre();
        $conducteur = $subject->getTrajet()?->getConducteur();

        return match ($attribute) {
            self::ANNULER => null !== $passager && $passager->getId() === $membre->getId(),
            self::NOTER => (null !== $passager && $passager->getId() === $membre->getId())
                || (null !== $conducteur && $conducteur->getId() === $membre->getId()),
            self::GERER => null !== $conducteur && $conducteur->getId() === $membre->getId(),
            default => false,
        };
    }
}
