<?php

namespace App\Controller\Admin;

use App\Entity\Site;
use App\Form\SiteType;
use App\Repository\SiteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Gestion des campus : créer, modifier, activer/désactiver (y compris
 * l'ajout de futurs campus ENI).
 */
final class SiteAdminController extends AbstractController
{
    public function __construct(
        private readonly SiteRepository $siteRepository,
    ) {
    }

    #[Route('/admin/campus', name: 'app_admin_site_index')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return $this->render('admin/site_index.html.twig', [
            'sites' => $this->siteRepository->findBy([], ['nom' => 'ASC']),
        ]);
    }

    #[Route('/admin/campus/nouveau', name: 'app_admin_site_nouveau')]
    public function nouveau(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $site = new Site();
        $form = $this->createForm(SiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($site);
            $entityManager->flush();

            $this->addFlash('success', 'Campus créé.');

            return $this->redirectToRoute('app_admin_site_index');
        }

        return $this->render('admin/site_form.html.twig', [
            'form' => $form,
            'titre' => 'Ajouter un campus',
        ]);
    }

    #[Route('/admin/campus/{id}/modifier', name: 'app_admin_site_modifier', requirements: ['id' => '\d+'])]
    public function modifier(Request $request, Site $site, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(SiteType::class, $site);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Campus modifié.');

            return $this->redirectToRoute('app_admin_site_index');
        }

        return $this->render('admin/site_form.html.twig', [
            'form' => $form,
            'titre' => 'Modifier le campus',
        ]);
    }

    #[Route('/admin/campus/{id}/basculer', name: 'app_admin_site_basculer', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function basculer(Request $request, Site $site, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($this->isCsrfTokenValid('basculer'.$site->getId(), (string) $request->request->get('_token'))) {
            $site->setActif(!$site->isActif());
            $entityManager->flush();
            $this->addFlash('success', $site->isActif()
                ? 'Campus activé : il reparaît dans les recherches et à l\'inscription.'
                : 'Campus désactivé : il disparaît des recherches et à l\'inscription.');
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
        }

        return $this->redirectToRoute('app_admin_site_index');
    }
}
