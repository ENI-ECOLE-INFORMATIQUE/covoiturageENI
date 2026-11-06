<?php

namespace App\Controller;

use App\Entity\Site;
use App\Entity\Trajet;
use App\Repository\TrajetRepository;
use App\Service\ImageCampus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class CampusController extends AbstractController
{
    public function __construct(
        private readonly TrajetRepository $trajetRepository,
        private readonly ImageCampus $imageCampus,
    ) {
    }

    #[Route('/campus/{id}', name: 'app_campus_show', requirements: ['id' => '\d+'])]
    public function show(Site $site): Response
    {
        // Même filtre que l'accueil : trajets ouverts à venir avec des places libres.
        $trajets = \array_values(\array_filter(
            $this->trajetRepository->rechercher($site),
            static fn (Trajet $trajet): bool => $trajet->getNbPlacesRestantes() > 0
        ));

        return $this->render('campus/show.html.twig', [
            'site' => $site,
            'image' => $this->imageCampus->pour($site),
            'trajets' => $trajets,
        ]);
    }
}
