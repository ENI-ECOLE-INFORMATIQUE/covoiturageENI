<?php

namespace App\Controller\Admin;

use App\Entity\Avis;
use App\Entity\Membre;
use App\Entity\Participation;
use App\Entity\Site;
use App\Entity\Trajet;
use App\Enum\StatutParticipation;
use App\Enum\StatutTrajet;
use App\Repository\AvisRepository;
use App\Repository\MembreRepository;
use App\Repository\ParticipationRepository;
use App\Repository\SiteRepository;
use App\Repository\TrajetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    public function __construct(
        private readonly MembreRepository $membreRepository,
        private readonly SiteRepository $siteRepository,
        private readonly TrajetRepository $trajetRepository,
        private readonly ParticipationRepository $participationRepository,
        private readonly AvisRepository $avisRepository,
    ) {
    }

    #[Route('/admin', name: 'app_admin_dashboard')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/dashboard.html.twig', [
            'nbMembres' => $this->membreRepository->count([]),
            'nbSitesActifs' => $this->siteRepository->count(['actif' => true]),
            'nbSitesInactifs' => $this->siteRepository->count(['actif' => false]),
            'nbTrajets' => $this->trajetRepository->count([]),
            'nbTrajetsOuverts' => $this->trajetRepository->count(['statut' => StatutTrajet::Ouvert]),
            'nbParticipationsAcceptees' => $this->participationRepository->count(['statut' => StatutParticipation::Accepte]),
            'nbAvis' => $this->avisRepository->count([]),
            'moyenneAvis' => $this->avisRepository->moyenneGenerale(),
            'trajetsParCampus' => $this->trajetRepository->compterOuvertsFutursParSite(),
        ]);
    }
}
