<?php

namespace App\Controller;

use App\Entity\Avis;
use App\Entity\Participation;
use App\Form\AvisType;
use App\Repository\AvisRepository;
use App\Security\Voter\ParticipationVoter;
use App\Service\EligibiliteAvis;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AvisController extends AbstractController
{
    public function __construct(
        private readonly EligibiliteAvis $eligibiliteAvis,
        #[Autowire('%app.delai_avis_heures%')]
        private readonly int $delaiAvisHeures,
    ) {
    }

    /**
     * Règle métier 6 : un avis, après le départ + 1 h, une seule fois par
     * participation acceptée. La cible est l'autre partie du trajet.
     */
    #[Route('/participation/{id}/avis', name: 'app_avis_nouveau', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function nouveau(Request $request, Participation $participation, EntityManagerInterface $entityManager, AvisRepository $avisRepository): Response
    {
        $this->denyAccessUnlessGranted(ParticipationVoter::NOTER, $participation);

        /** @var \App\Entity\Membre $auteur */
        $auteur = $this->getUser();
        $redirection = $this->redirectToRoute('app_mes_trajets');

        if (!$this->eligibiliteAvis->peutEtreNotee($participation)) {
            $this->addFlash('warning', \sprintf(
                'Un avis n\'est possible que pour une participation acceptée, %d heure(s) après le départ.',
                $this->delaiAvisHeures
            ));

            return $redirection;
        }

        if (null !== $avisRepository->findOneBy(['participation' => $participation])) {
            $this->addFlash('warning', 'Un avis a déjà été laissé pour ce trajet.');

            return $redirection;
        }

        $trajet = $participation->getTrajet();
        $cible = ($auteur->getId() === $trajet->getConducteur()->getId())
            ? $participation->getMembre()
            : $trajet->getConducteur();

        $avis = new Avis();
        $avis->setAuteur($auteur);
        $avis->setCible($cible);
        $avis->setParticipation($participation);

        $form = $this->createForm(AvisType::class, $avis);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($avis);
            $entityManager->flush();

            $this->addFlash('success', 'Merci, votre avis est publié !');

            return $this->redirectToRoute('app_profil', ['id' => $cible->getId()]);
        }

        return $this->render('avis/nouveau.html.twig', [
            'form' => $form,
            'participation' => $participation,
            'cible' => $cible,
        ]);
    }
}
