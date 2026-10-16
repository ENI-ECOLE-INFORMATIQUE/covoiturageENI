<?php

namespace App\Service;

use App\Entity\Participation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mime\Address;

/**
 * Notifications email, expédiées de façon asynchrone via Messenger
 * (transport Doctrine : table messenger_messages).
 *
 * Consommer la file : bin/console messenger:consume async
 */
final class Notifier
{
    private const EXPEDITEUR_EMAIL = 'no-reply@campus-eni.fr';
    private const EXPEDITEUR_NOM = 'ENI Covoiturage';

    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function nouvelleDemande(Participation $participation): void
    {
        $this->envoyer(
            $participation->getTrajet()->getConducteur()->getEmail(),
            'ENI Covoiturage : nouvelle demande de participation',
            'emails/nouvelle_demande.html.twig',
            $participation
        );
    }

    public function demandeAcceptee(Participation $participation): void
    {
        $this->envoyer(
            $participation->getMembre()->getEmail(),
            'ENI Covoiturage : votre demande a été acceptée',
            'emails/demande_acceptee.html.twig',
            $participation
        );
    }

    public function demandeRefusee(Participation $participation): void
    {
        $this->envoyer(
            $participation->getMembre()->getEmail(),
            'ENI Covoiturage : votre demande a été refusée',
            'emails/demande_refusee.html.twig',
            $participation
        );
    }

    public function trajetAnnule(Participation $participation): void
    {
        $this->envoyer(
            $participation->getMembre()->getEmail(),
            'ENI Covoiturage : un trajet auquel vous participiez a été annulé',
            'emails/trajet_annule.html.twig',
            $participation
        );
    }

    private function envoyer(string $destinataire, string $sujet, string $template, Participation $participation): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address(self::EXPEDITEUR_EMAIL, self::EXPEDITEUR_NOM))
            ->to($destinataire)
            ->subject($sujet)
            ->htmlTemplate($template)
            ->context(['participation' => $participation]);

        $this->bus->dispatch(new SendEmailMessage($email));
    }
}
