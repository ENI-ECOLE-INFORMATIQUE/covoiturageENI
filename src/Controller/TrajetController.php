<?php

namespace App\Controller;

use App\Entity\Trajet;
use App\Enum\StatutParticipation;
use App\Enum\StatutTrajet;
use App\Form\TrajetType;
use App\Repository\TrajetRepository;
use App\Repository\VehiculeRepository;
use App\Security\Voter\TrajetVoter;
use App\Service\EligibiliteAvis;
use App\Service\Notifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class TrajetController extends AbstractController
{
    public function __construct(
        private readonly TrajetRepository $trajetRepository,
        private readonly VehiculeRepository $vehiculeRepository,
        private readonly EligibiliteAvis $eligibiliteAvis,
        private readonly Notifier $notifier,
        #[Autowire('%app.delai_reservation_heures%')]
        private readonly int $delaiReservationHeures,
    ) {
    }

    #[Route('/trajet/nouveau', name: 'app_trajet_nouveau')]
    public function nouveau(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\Membre $conducteur */
        $conducteur = $this->getUser();

        if (0 === $this->vehiculeRepository->count(['membre' => $conducteur])) {
            $this->addFlash('warning', 'Ajoutez d\'abord un véhicule pour publier un trajet.');

            return $this->redirectToRoute('app_vehicule_nouveau');
        }

        $trajet = new Trajet();
        $trajet->setConducteur($conducteur);
        $trajet->setStatut(StatutTrajet::Ouvert);

        $form = $this->createForm(TrajetType::class, $trajet, ['conducteur' => $conducteur]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Le nombre de places offertes suit le véhicule choisi (spec MCD).
            $trajet->setNbPlacesOffertes($trajet->getVehicule()->getNbPlaces());

            $entityManager->persist($trajet);
            $entityManager->flush();

            $this->addFlash('success', 'Trajet publié.');

            return $this->redirectToRoute('app_trajet_show', ['id' => $trajet->getId()]);
        }

        return $this->render('trajet/form.html.twig', [
            'form' => $form,
            'titre' => 'Publier un trajet',
        ]);
    }

    #[Route('/trajet/{id}', name: 'app_trajet_show', requirements: ['id' => '\d+'])]
    public function show(Request $request, Trajet $trajet, EntityManagerInterface $entityManager): Response
    {
        /** @var ?\App\Entity\Membre $membre */
        $membre = $this->getUser();

        $maParticipation = null;
        if (null !== $membre) {
            $maParticipation = $entityManager->getRepository(\App\Entity\Participation::class)
                ->findOneBy(['trajet' => $trajet, 'membre' => $membre]);
        }

        // Règle métier 5 : réservation fermée X heures avant le départ.
        $limitesDepassees = $trajet->getDateDepart() <= (new \DateTimeImmutable())
            ->modify(\sprintf('+%d hours', $this->delaiReservationHeures));

        $peutReserver = null !== $membre
            && null === $maParticipation
            && $trajet->getConducteur()->getId() !== $membre->getId()
            && StatutTrajet::Ouvert === $trajet->getStatut()
            && $trajet->getNbPlacesRestantes() > 0
            && !$limitesDepassees;

        $formReservation = null;
        if ($peutReserver) {
            $formReservation = $this->createForm(\App\Form\ReservationType::class, new \App\Entity\Participation(), [
                'places_restantes' => $trajet->getNbPlacesRestantes(),
                'action' => $this->generateUrl('app_participation_reserver', ['id' => $trajet->getId()]),
            ]);
        }

        return $this->render('trajet/show.html.twig', [
            'trajet' => $trajet,
            'maParticipation' => $maParticipation,
            'peutReserver' => $peutReserver,
            'formReservation' => $formReservation,
            'delaiDepasse' => $limitesDepassees,
        ]);
    }

    #[Route('/trajet/{id}/modifier', name: 'app_trajet_modifier', requirements: ['id' => '\d+'])]
    public function modifier(Request $request, Trajet $trajet, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(TrajetVoter::EDITER, $trajet);

        if (StatutTrajet::Ouvert !== $trajet->getStatut()) {
            $this->addFlash('danger', 'Seul un trajet ouvert peut être modifié.');

            return $this->redirectToRoute('app_trajet_show', ['id' => $trajet->getId()]);
        }

        $form = $this->createForm(TrajetType::class, $trajet, ['conducteur' => $trajet->getConducteur()]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $placesAcceptees = 0;
            foreach ($trajet->getParticipations() as $participation) {
                if (StatutParticipation::Accepte === $participation->getStatut()) {
                    $placesAcceptees += $participation->getNbPlaces();
                }
            }

            if ($trajet->getVehicule()->getNbPlaces() < $placesAcceptees) {
                $this->addFlash('danger', 'Ce véhicule a moins de places que les réservations déjà acceptées.');

                return $this->render('trajet/form.html.twig', [
                    'form' => $form,
                    'titre' => 'Modifier le trajet',
                ]);
            }

            $trajet->setNbPlacesOffertes($trajet->getVehicule()->getNbPlaces());
            $entityManager->flush();

            $this->addFlash('success', 'Trajet modifié.');

            return $this->redirectToRoute('app_trajet_show', ['id' => $trajet->getId()]);
        }

        return $this->render('trajet/form.html.twig', [
            'form' => $form,
            'titre' => 'Modifier le trajet',
        ]);
    }

    #[Route('/trajet/{id}/annuler', name: 'app_trajet_annuler', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function annuler(Request $request, Trajet $trajet, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(TrajetVoter::ANNULER, $trajet);

        if (!$this->isCsrfTokenValid('annuler'.$trajet->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        } elseif (!\in_array($trajet->getStatut(), [StatutTrajet::Ouvert, StatutTrajet::Complet], true)) {
            $this->addFlash('warning', 'Ce trajet a déjà été annulé ou terminé.');
        } else {
            // Règle métier 4 : toutes les participations vivantes sont annulées.
            $trajet->setStatut(StatutTrajet::Annule);
            foreach ($trajet->getParticipations() as $participation) {
                if (\in_array($participation->getStatut(), [StatutParticipation::EnAttente, StatutParticipation::Accepte], true)) {
                    $participation->setStatut(StatutParticipation::Annule);
                }
            }
            $entityManager->flush();

            // Règle métier 4 : chaque participant est notifié.
            foreach ($trajet->getParticipations() as $participation) {
                if (StatutParticipation::Annule === $participation->getStatut()) {
                    $this->notifier->trajetAnnule($participation);
                }
            }

            $this->addFlash('success', 'Trajet annulé : les participants en sont informés.');
        }

        return $this->redirectToRoute('app_trajet_show', ['id' => $trajet->getId()]);
    }

    #[Route('/mes-trajets', name: 'app_mes_trajets')]
    public function mesTrajets(EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\Membre $membre */
        $membre = $this->getUser();

        $participations = $entityManager->getRepository(\App\Entity\Participation::class)
            ->findBy(['membre' => $membre], ['creeLe' => 'DESC']);

        // Règle métier 6 : le passager peut noter les participations éligibles.
        $participationsVue = [];
        foreach ($participations as $participation) {
            $participationsVue[] = [
                'participation' => $participation,
                'peutLaisserAvis' => $this->eligibiliteAvis->peutLaisserAvis($participation),
                'dejaNote' => null !== $entityManager->getRepository(\App\Entity\Avis::class)->findOneBy(['participation' => $participation]),
            ];
        }

        return $this->render('trajet/mes_trajets.html.twig', [
            'trajetsConduits' => $this->trajetRepository->findByConducteur($membre->getId()),
            'mesParticipations' => $participationsVue,
        ]);
    }
}
