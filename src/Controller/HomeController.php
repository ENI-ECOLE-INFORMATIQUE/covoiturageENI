<?php

namespace App\Controller;

use App\Repository\SiteRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(SiteRepository $siteRepository): Response
    {
        return $this->render('home/index.html.twig', [
            'sites' => $siteRepository->findBy(['actif' => true], ['nom' => 'ASC']),
        ]);
    }
}
