<?php

namespace App\Controller\Admin;

use App\Entity\Avis;
use App\Repository\AvisRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Modération des avis : consultation et suppression.
 */
final class AvisAdminController extends AbstractController
{
    public function __construct(
        private readonly AvisRepository $avisRepository,
    ) {
    }

    #[Route('/admin/avis', name: 'app_admin_avis_index')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/avis_index.html.twig', [
            'avis' => $this->avisRepository->findBy([], ['creeLe' => 'DESC']),
        ]);
    }

    #[Route('/admin/avis/{id}/supprimer', name: 'app_admin_avis_supprimer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function supprimer(Request $request, Avis $avis, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('supprimer'.$avis->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($avis);
            $entityManager->flush();
            $this->addFlash('success', 'Avis supprimé.');
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('app_admin_avis_index');
    }
}
