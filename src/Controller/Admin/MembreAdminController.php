<?php

namespace App\Controller\Admin;

use App\Entity\Membre;
use App\Repository\MembreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Modération des comptes : désactiver / réactiver un membre.
 */
final class MembreAdminController extends AbstractController
{
    public function __construct(
        private readonly MembreRepository $membreRepository,
    ) {
    }

    #[Route('/admin/membres', name: 'app_admin_membre_index')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/membre_index.html.twig', [
            'membres' => $this->membreRepository->findBy([], ['nom' => 'ASC', 'prenom' => 'ASC']),
        ]);
    }

    #[Route('/admin/membres/{id}/basculer', name: 'app_admin_membre_basculer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function basculer(Request $request, Membre $membre, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        /** @var Membre $admin */
        $admin = $this->getUser();

        if ($membre->getId() === $admin->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas désactiver votre propre compte.');
        } elseif ($this->isCsrfTokenValid('basculer'.$membre->getId(), (string) $request->request->get('_token'))) {
            $membre->setActif(!$membre->isActif());
            $entityManager->flush();
            $this->addFlash('success', $membre->isActif()
                ? 'Compte réactivé.'
                : 'Compte désactivé : la connexion est désormais refusée.');
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('app_admin_membre_index');
    }
}
