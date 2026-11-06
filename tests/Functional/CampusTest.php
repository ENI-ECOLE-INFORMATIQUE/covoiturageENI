<?php

namespace App\Tests\Functional;

use App\Repository\SiteRepository;
use App\Tests\DatabasePrimerTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class CampusTest extends WebTestCase
{
    use DatabasePrimerTrait;

    /**
     * Page détail d'un campus : image, adresse, site web, présentation
     * et annonces de covoiturage du campus.
     */
    public function testPageDetailCampus(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());

        $siteNantes = $client->getContainer()->get(SiteRepository::class)->findOneBy(['nom' => 'ENI Nantes']);
        $crawler = $client->request('GET', '/campus/'.$siteNantes->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'ENI Nantes');
        $this->assertSelectorTextContains('body', '2B rue Benjamin Franklin / 3 rue Mickaël Faraday - 44800 SAINT-HERBLAIN');
        $this->assertSelectorTextContains('body', 'https://www.eni-ecole.fr/campus/nantes');
        $this->assertSelectorTextContains('h2', 'Présentation : Nantes');
        $this->assertSelectorTextContains('body', 'Le covoiturage est une excellente solution');
        $this->assertSelectorTextContains('body', 'Annonces de covoiturage');
        // Image du campus
        $img = $crawler->filter('img.card-img-top')->first();
        $this->assertStringContainsString('poster_thumb_facade-nantes-franklin', (string) $img->attr('src'));
        // Aucun trajet de démo sur Nantes
        $this->assertSelectorTextContains('body', 'Aucune annonce de covoiturage pour ce campus');
    }

    /**
     * La page d'un campus avec des trajets liste les annonces disponibles.
     */
    public function testPageDetailCampusListeSesTrajets(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());

        $siteRennes = $client->getContainer()->get(SiteRepository::class)->findOneBy(['nom' => 'ENI Rennes']);
        $client->request('GET', '/campus/'.$siteRennes->getId());

        $this->assertResponseIsSuccessful();
        // Les trajets de démo sont sur le campus de Rennes (Vannes -> Rennes).
        $this->assertSelectorTextContains('body', 'Vannes');
        $this->assertSelectorTextContains('body', 'Voir le trajet');
    }

    public function testCampusInconnuRenvoie404(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());

        $client->request('GET', '/campus/999999');

        $this->assertResponseStatusCodeSame(404);
    }
}
