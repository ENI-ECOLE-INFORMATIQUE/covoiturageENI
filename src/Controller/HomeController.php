<?php

namespace App\Controller;

use App\Entity\Site;
use App\Entity\Trajet;
use App\Form\TrajetSearchType;
use App\Repository\SiteRepository;
use App\Repository\TrajetRepository;
use App\Service\ImageCampus;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    public function __construct(
        private readonly TrajetRepository $trajetRepository,
        private readonly SiteRepository $siteRepository,
        private readonly ImageCampus $imageCampus,
    ) {
    }

    #[Route('/', name: 'app_home')]
    public function index(Request $request): Response
    {
        $form = $this->createForm(TrajetSearchType::class);
        $form->handleRequest($request);

        $trajets = $this->trajetRepository->rechercher(
            $form->get('site')->getData(),
            $form->get('sens')->getData(),
            $form->get('date')->getData(),
            $form->get('ville')->getData(),
        );

        // Filtre métier : seuls les trajets avec au moins une place libre
        // sont proposés (capacité = participations acceptées).
        $trajets = \array_values(\array_filter(
            $trajets,
            static fn (Trajet $trajet): bool => $trajet->getNbPlacesRestantes() > 0
        ));

        return $this->render('home/index.html.twig', [
            'formRecherche' => $form,
            'trajets' => $trajets,
            'campus' => $this->campusActifs(),
        ]);
    }

    /**
     * @return array<int, array{site: Site, image: string}>
     */
    private function campusActifs(): array
    {
        $campus = [];
        foreach ($this->siteRepository->findBy(['actif' => true], ['nom' => 'ASC']) as $site) {
            $campus[] = ['site' => $site, 'image' => $this->imageCampus->pour($site)];
        }

        return $campus;
    }
}
