<?php

namespace App\Controller;

use App\Entity\Membre;
use App\Repository\AvisRepository;
use App\Repository\TrajetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfilController extends AbstractController
{
    public function __construct(
        private readonly AvisRepository $avisRepository,
        private readonly TrajetRepository $trajetRepository,
    ) {
    }

    #[Route('/membre/{id}', name: 'app_profil', requirements: ['id' => '\d+'])]
    public function show(Membre $membre): Response
    {
        return $this->render('profil/show.html.twig', [
            'membre' => $membre,
            'moyenne' => $this->avisRepository->moyennePour($membre),
            'avis' => $this->avisRepository->findBy(['cible' => $membre], ['creeLe' => 'DESC'], 20),
            'trajetsAvenir' => $this->trajetRepository->findFutursOuvertsPour($membre),
        ]);
    }
}
