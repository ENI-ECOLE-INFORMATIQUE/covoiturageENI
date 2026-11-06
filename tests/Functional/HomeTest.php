<?php

namespace App\Tests\Functional;

use App\Repository\SiteRepository;
use App\Tests\DatabasePrimerTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomeTest extends WebTestCase
{
    use DatabasePrimerTrait;

    /**
     * La page d'accueil reprend la structure de la page d'atterrissage ENI :
     * hero de bienvenue, avantages, cartes des campus avec image/adresse/bouton.
     */
    public function testPageAccueilInspireeDuSiteEni(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());

        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Hero
        $this->assertSelectorTextContains('h1', 'Bienvenue sur votre plateforme de covoiturage');
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('banner-ecologique_conviviale', $html, 'Le hero doit utiliser la bannière.');

        // Avantages
        $this->assertSelectorTextContains('body', 'Pourquoi utiliser le covoiturage ENI ?');
        foreach (['Économique', 'Écologique', 'Convivial', 'Pratique'] as $avantage) {
            $this->assertSelectorTextContains('body', $avantage);
        }

        // Une carte par campus actif, avec image et nom
        $this->assertSelectorTextContains('body', 'Nos campus');
        $sites = $client->getContainer()->get(SiteRepository::class)->findBy(['actif' => true]);
        $images = $crawler->filter('img.card-img-top');
        $this->assertCount(\count($sites), $images, 'Une image par campus actif.');
        // Maximum 3 campus par ligne : chaque carte occupe col-md-4.
        $colonnes = $crawler->filter('.campus-col');
        $this->assertCount(\count($sites), $colonnes, 'Chaque carte campus doit avoir sa propre colonne.');
        foreach ($colonnes->each(static fn ($n) => (string) $n->attr('class')) as $class) {
            $this->assertStringContainsString('col-md-4', $class, 'Max 3 campus par ligne.');
        }
        $srcs = \implode(' ', $images->each(static fn ($n) => (string) $n->attr('src')));
        $this->assertStringContainsString('poster_thumb_batiment-rennes', $srcs);
        $this->assertStringContainsString('poster_thumb_facade-nantes-franklin', $srcs);
        $this->assertStringContainsString('poster_thumb_batiment-quimper', $srcs);
        $this->assertStringContainsString('poster_thumb_batiment-niort', $srcs);

        // Adresse affichée sur la carte
        $this->assertSelectorTextContains('body', '8 Rue Léo Lagrange, Chartres de Bretagne (35131)');
        $this->assertSelectorTextContains('body', '19 avenue Léo Lagrange, Bâtiment B et C, Niort (79000)');

        // Bouton Covoiturer : lien vers la recherche préfiltrée sur le campus
        $siteRennes = $client->getContainer()->get(SiteRepository::class)->findOneBy(['nom' => 'ENI Rennes']);
        $liens = $crawler->filter('a')->reduce(
            static fn ($node): bool => str_contains($node->text(), 'Covoiturer')
                && str_contains((string) $node->attr('href'), 'trajet_search')
                && str_contains((string) $node->attr('href'), (string) $siteRennes->getId())
        );
        $this->assertCount(1, $liens, 'Le bouton Covoiturer du campus Rennes doit préfiltrer la recherche.');

        // Bouton Détails : lien vers la page du campus
        $details = $crawler->filter('a')->reduce(
            static fn ($node): bool => str_contains($node->text(), 'Détails')
                && (string) $node->attr('href') === '/campus/'.$siteRennes->getId()
        );
        $this->assertCount(1, $details, 'Le bouton Détails du campus Rennes doit mener à /campus/{id}.');
    }

    /**
     * Le lien « Covoiturer » d'un campus préremplit le filtre campus de la recherche.
     */
    public function testLienCovoiturerPrefiltreLaRecherche(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());

        $siteRennes = $client->getContainer()->get(SiteRepository::class)->findOneBy(['nom' => 'ENI Rennes']);
        $client->request('GET', '/?trajet_search%5Bsite%5D='.$siteRennes->getId());

        $this->assertResponseIsSuccessful();
        // Les trajets de démo sont sur le campus de Rennes (ville de départ Vannes).
        $this->assertSelectorTextContains('body', 'Vannes');

        $siteNantes = $client->getContainer()->get(SiteRepository::class)->findOneBy(['nom' => 'ENI Nantes']);
        $client->request('GET', '/?trajet_search%5Bsite%5D='.$siteNantes->getId());
        $this->assertSelectorTextContains('body', 'Aucun trajet');
    }

    /**
     * Carte interactive OpenStreetMap sous « Trajets disponibles » :
     * conteneur Leaflet, filtre par campus, données des trajets injectées.
     */
    public function testCarteOpenStreetMapDesTrajets(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());

        $crawler = $client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        // Conteneur de la carte
        $this->assertCount(1, $crawler->filter('#carte-trajets'), 'Le conteneur de la carte doit exister.');

        // Filtre par campus : « Tous les campus » + un option par campus actif
        $sites = $client->getContainer()->get(SiteRepository::class)->findBy(['actif' => true]);
        $options = $crawler->filter('#filtre-campus-carte option');
        $this->assertCount(\count($sites) + 1, $options);
        $this->assertSame('Tous les campus', trim($options->first()->text()));

        // Leaflet + tuiles OSM chargés, données des trajets injectées en JSON
        $html = (string) $client->getResponse()->getContent();
        $this->assertStringContainsString('unpkg.com/leaflet', $html);
        $this->assertStringContainsString('tile.openstreetmap.org', $html);
        $this->assertMatchesRegularExpression('/const TRAJETS = \[/', $html);
        $this->assertStringContainsString('villeDepart', $html);
        $this->assertStringContainsString('Vannes', $html); // trajets de démo
    }
}
