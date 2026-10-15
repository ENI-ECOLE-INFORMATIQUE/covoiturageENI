<?php

namespace App\Security;

use App\Entity\Membre;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Bloque la connexion des comptes désactivés ou non vérifiés.
 */
final class MembreUserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Membre) {
            return;
        }

        if (!$user->isActif()) {
            throw new CustomUserMessageAccountStatusException('Ce compte a été désactivé par un administrateur.');
        }

        if (null === $user->getEmailVerifieLe()) {
            throw new CustomUserMessageAccountStatusException('Votre adresse email n\'a pas encore été vérifiée : consultez votre boîte de réception.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // Rien à contrôler après authentification.
    }
}
