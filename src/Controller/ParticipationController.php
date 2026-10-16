<?php

namespace App\Controller;

use App\Entity\Participation;
use App\Entity\Trajet;
use App\Enum\StatutParticipation;
use App\Enum\StatutTrajet;
use App\Form\ReservationType;
use App\Security\Voter\ParticipationVoter;
use App\Service\Notifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ParticipationController extends AbstractController
{
    public function __construct(
        private readonly Notifier $notifier,
        #[Autowire('%app.delai_reservation_heures%')]
        private readonly int $delaiReservationHeures,
    ) {
    }

    #[Route('/trajet/{id}/reserver', name: 'app_participation_reserver', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reserver(Request $request, Trajet $trajet, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\Membre $membre */
        $membre = $this->getUser();

        $redirection = $this->redirectToRoute('app_trajet_show', ['id' => $trajet->getId()]);

        // Règle métier 1 : le conducteur ne réserve pas son propre trajet.
        if ($trajet->getConducteur()->getId() === $membre->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas réserver votre propre trajet.');

            return $redirection;
        }

        if (null !== $entityManager->getRepository(Participation::class)->findOneBy(['trajet' => $trajet, 'membre' => $membre])) {
            $this->addFlash('warning', 'Vous avez déjà une demande en cours sur ce trajet.');

            return $redirection;
        }

        if (StatutTrajet::Ouvert !== $trajet->getStatut() || $trajet->getNbPlacesRestantes() < 1) {
            $this->addFlash('danger', 'Ce trajet n\'accepte plus de réservation.');

            return $redirection;
        }

        // Règle métier 5 : réservation impossible dans les X heures avant le départ.
        $limite = (new \DateTimeImmutable())->modify(\sprintf('+%d hours', $this->delaiReservationHeures));
        if ($trajet->getDateDepart() <= $limite) {
            $this->addFlash('danger', \sprintf(
                'La réservation ferme %d heure(s) avant le départ.',
                $this->delaiReservationHeures
            ));

            return $redirection;
        }

        $participation = new Participation();
        $form = $this->createForm(ReservationType::class, $participation, [
            'places_restantes' => $trajet->getNbPlacesRestantes(),
        ]);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('danger', 'Demande invalide (nombre de places ou jeton de sécurité).');

            return $redirection;
        }

        $participation->setTrajet($trajet);
        $participation->setMembre($membre);
        $participation->setStatut(StatutParticipation::EnAttente);

        $entityManager->persist($participation);
        $entityManager->flush();

        $this->notifier->nouvelleDemande($participation);
        $this->addFlash('success', 'Demande envoyée au conducteur.');

        return $redirection;
    }

    #[Route('/participation/{id}/accepter', name: 'app_participation_accepter', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function accepter(Request $request, Participation $participation, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(ParticipationVoter::GERER, $participation);
        $trajet = $participation->getTrajet();

        if (!$this->isCsrfTokenValid('accepter'.$participation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        } elseif (StatutParticipation::EnAttente !== $participation->getStatut()) {
            $this->addFlash('warning', 'Cette demande a déjà été traitée.');
        } elseif ($participation->getNbPlaces() > $trajet->getNbPlacesRestantes()) {
            // Règle métier 2 : pas plus de places acceptées que de places offertes.
            $this->addFlash('danger', 'Places insuffisantes pour accepter cette demande.');
        } else {
            $participation->setStatut(StatutParticipation::Accepte);

            if (0 === $trajet->getNbPlacesRestantes()) {
                $trajet->setStatut(StatutTrajet::Complet);
            }

            $entityManager->flush();

            $this->notifier->demandeAcceptee($participation);
            $this->addFlash('success', 'Demande acceptée.');
        }

        return $this->redirectToRoute('app_trajet_show', ['id' => $trajet->getId()]);
    }

    #[Route('/participation/{id}/refuser', name: 'app_participation_refuser', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refuser(Request $request, Participation $participation, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(ParticipationVoter::GERER, $participation);
        $trajet = $participation->getTrajet();

        if (!$this->isCsrfTokenValid('refuser'.$participation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        } elseif (StatutParticipation::EnAttente !== $participation->getStatut()) {
            $this->addFlash('warning', 'Cette demande a déjà été traitée.');
        } else {
            // Règle métier 3 : le conducteur peut refuser une réservation.
            $participation->setStatut(StatutParticipation::Refuse);
            $entityManager->flush();

            $this->notifier->demandeRefusee($participation);
            $this->addFlash('success', 'Demande refusée.');
        }

        return $this->redirectToRoute('app_trajet_show', ['id' => $trajet->getId()]);
    }

    #[Route('/participation/{id}/annuler', name: 'app_participation_annuler', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function annuler(Request $request, Participation $participation, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(ParticipationVoter::ANNULER, $participation);
        $trajet = $participation->getTrajet();

        if (!$this->isCsrfTokenValid('annuler'.$participation->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        } elseif (!\in_array($participation->getStatut(), [StatutParticipation::EnAttente, StatutParticipation::Accepte], true)) {
            $this->addFlash('warning', 'Cette participation est déjà clôturée.');
        } else {
            $participation->setStatut(StatutParticipation::Annule);

            // Une place se libère : un trajet complet repasse ouvert.
            if (StatutTrajet::Complet === $trajet->getStatut() && $trajet->getNbPlacesRestantes() > 0) {
                $trajet->setStatut(StatutTrajet::Ouvert);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Participation annulée.');
        }

        return $this->redirectToRoute('app_trajet_show', ['id' => $trajet->getId()]);
    }
}
