<?php

namespace App\Controller;

use App\Entity\Vehicule;
use App\Form\VehiculeType;
use App\Repository\VehiculeRepository;
use App\Security\Voter\VehiculeVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class VehiculeController extends AbstractController
{
    public function __construct(
        private readonly VehiculeRepository $vehiculeRepository,
    ) {
    }

    #[Route('/vehicules', name: 'app_vehicule_index')]
    public function index(): Response
    {
        /** @var \App\Entity\Membre $membre */
        $membre = $this->getUser();

        return $this->render('vehicule/index.html.twig', [
            'vehicules' => $this->vehiculeRepository->findBy(['membre' => $membre]),
        ]);
    }

    #[Route('/vehicule/nouveau', name: 'app_vehicule_nouveau')]
    public function nouveau(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\Membre $membre */
        $membre = $this->getUser();

        $vehicule = new Vehicule();
        $vehicule->setMembre($membre);

        $form = $this->createForm(VehiculeType::class, $vehicule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($vehicule);
            $entityManager->flush();

            $this->addFlash('success', 'Véhicule enregistré.');

            return $this->redirectToRoute('app_vehicule_index');
        }

        return $this->render('vehicule/form.html.twig', [
            'form' => $form,
            'titre' => 'Ajouter un véhicule',
        ]);
    }

    #[Route('/vehicule/{id}/modifier', name: 'app_vehicule_modifier', requirements: ['id' => '\d+'])]
    public function modifier(Request $request, Vehicule $vehicule, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(VehiculeVoter::EDITER, $vehicule);

        $form = $this->createForm(VehiculeType::class, $vehicule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Véhicule modifié.');

            return $this->redirectToRoute('app_vehicule_index');
        }

        return $this->render('vehicule/form.html.twig', [
            'form' => $form,
            'titre' => 'Modifier le véhicule',
        ]);
    }

    #[Route('/vehicule/{id}/supprimer', name: 'app_vehicule_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function supprimer(Request $request, Vehicule $vehicule, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted(VehiculeVoter::SUPPRIMER, $vehicule);

        if ($this->isCsrfTokenValid('supprimer'.$vehicule->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($vehicule);
            $entityManager->flush();
            $this->addFlash('success', 'Véhicule supprimé. Les trajets publiés avec ce véhicule conservent leurs places offertes.');
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('app_vehicule_index');
    }
}
