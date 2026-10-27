<?php

namespace App\Security;

use App\Entity\Membre;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Génération et validation du lien signé de vérification d'email.
 */
final class EmailVerifier
{
    public function __construct(
        private readonly VerifyEmailHelperInterface $verifyEmailHelper,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function sendEmailConfirmation(string $routeVerification, Membre $membre, TemplatedEmail $email): void
    {
        $signature = $this->verifyEmailHelper->generateSignature(
            $routeVerification,
            (string) $membre->getId(),
            $membre->getEmail(),
            ['id' => $membre->getId()]
        );

        $email->context([
            'nomComplet' => $membre->getNomComplet(),
            'signedUrl' => $signature->getSignedUrl(),
            'expiresAt' => $signature->getExpiresAt(),
        ]);

        $this->mailer->send($email);
    }

    /**
     * @throws VerifyEmailExceptionInterface si le lien est invalide ou expiré
     */
    public function handleEmailConfirmation(Membre $membre, Request $request): void
    {
        $this->verifyEmailHelper->validateEmailConfirmationFromRequest($request, (string) $membre->getId(), $membre->getEmail());
    }
}
