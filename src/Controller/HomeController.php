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
            'trajetsCarteJson' => $this->trajetsPourCarte($trajets),
        ]);
    }

    /**
     * Données JSON des trajets pour la carte OpenStreetMap (accueil).
     *
     * @param Trajet[] $trajets
     */
    private function trajetsPourCarte(array $trajets): string
    {
        $data = \array_map(fn (Trajet $trajet): array => [
            'id' => $trajet->getId(),
            'villeDepart' => (string) $trajet->getVilleDepart(),
            'villeArrivee' => (string) $trajet->getVilleArrivee(),
            'dateDepart' => $trajet->getDateDepart()?->format('d/m/Y à H:i'),
            'prix' => (string) $trajet->getPrix(),
            'places' => $trajet->getNbPlacesRestantes(),
            'sens' => $trajet->getSens()?->libelle(),
            'siteId' => $trajet->getSite()?->getId(),
            'siteNom' => (string) $trajet->getSite()?->getNom(),
            'url' => $this->generateUrl('app_trajet_show', ['id' => $trajet->getId()]),
        ], $trajets);

        // Flags hex : le JSON peut être injecté sans risque dans un <script>.
        return (string) \json_encode($data, \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT);
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
