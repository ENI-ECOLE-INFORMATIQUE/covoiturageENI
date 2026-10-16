<?php

namespace App\Tests\Functional;

use App\Entity\Membre;
use App\Entity\Site;
use App\Repository\AvisRepository;
use App\Repository\MembreRepository;
use App\Repository\SiteRepository;
use App\Tests\DatabasePrimerTrait;
use App\Tests\TrajetHelpersTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * M4 : parcours administrateur complet — dashboard, campus, membres, avis.
 */
final class AdminTest extends WebTestCase
{
    use DatabasePrimerTrait;
    use TrajetHelpersTrait;

    private static function admin(KernelBrowser $client): Membre
    {
        $admin = $client->getContainer()->get(MembreRepository::class)
            ->findOneBy(['email' => 'admin@campus-eni.fr']);
        \assert(null !== $admin);

        return $admin;
    }

    public function testAccesRefuseAuxNonAdmins(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());

        // Anonyme : redirection vers la connexion.
        $client->request('GET', '/admin');
        $this->assertResponseRedirects('/connexion');

        // Membre standard : accès refusé.
        $membre = self::creerMembre($client, 'lambda@campus-eni.fr');
        $client->loginUser($membre);
        $client->request('GET', '/admin');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testDashboardAfficheLesStatistiques(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());

        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'));

        $client->loginUser(self::admin($client));
        $crawler = $client->request('GET', '/admin');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Statistiques');
        $this->assertSelectorTextContains('body', 'Trajets ouverts à venir par campus');
        $this->assertSelectorTextContains('body', 'ENI Rennes'); // fixtures : 2 trajets ouverts
        $this->assertSelectorTextContains('body', 'Admin'); // lien de navigation
    }

    public function testGestionCompleteDesCampus(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $client->loginUser(self::admin($client));

        // Création.
        $crawler = $client->request('GET', '/admin/campus/nouveau');
        $form = $crawler->selectButton('Enregistrer le campus')->form([
            'site[nom]' => 'ENI Laval',
            'site[ville]' => 'Laval',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/admin/campus');

        $site = $client->getContainer()->get(SiteRepository::class)->findOneBy(['nom' => 'ENI Laval']);
        $this->assertNotNull($site, 'Le campus doit être créé.');
        $this->assertTrue($site->isActif());

        // Le campus actif apparaît dans le filtre de recherche de l'accueil.
        $client->request('GET', '/');
        $this->assertSelectorTextContains('body', 'ENI Laval');

        // Modification.
        $crawler = $client->request('GET', '/admin/campus/'.$site->getId().'/modifier');
        $form = $crawler->selectButton('Enregistrer le campus')->form([
            'site[nom]' => 'ENI Laval Centre',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/admin/campus');

        // Désactivation : le campus disparaît de l'inscription.
        $site = $client->getContainer()->get(SiteRepository::class)->find($site->getId());
        $crawler = $client->request('GET', '/admin/campus');
        $formulaire = $crawler->filter('form[action*="/admin/campus/'.$site->getId().'/basculer"]')->form();
        $client->submit($formulaire);
        $this->assertResponseRedirects('/admin/campus');

        $site = $client->getContainer()->get(SiteRepository::class)->find($site->getId());
        $this->assertFalse($site->isActif(), 'Le campus doit être désactivé.');

        $client->request('GET', '/');
        $this->assertSelectorTextNotContains('body', 'ENI Laval Centre');

        // Réactivation.
        $crawler = $client->request('GET', '/admin/campus');
        $formulaire = $crawler->filter('form[action*="/admin/campus/'.$site->getId().'/basculer"]')->form();
        $client->submit($formulaire);

        $site = $client->getContainer()->get(SiteRepository::class)->find($site->getId());
        $this->assertTrue($site->isActif(), 'Le campus doit être réactivé.');
    }

    public function testDesactivationMembreEtGardeAntiAutoDesactivation(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $cible = self::creerMembre($client, 'cible@campus-eni.fr');
        $admin = self::admin($client);

        // L'admin ne peut pas se désactiver lui-même.
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/membres');
        $this->assertSelectorTextContains('body', 'vous'); // pas de bouton pour soi-même
        $this->assertSame(0, $crawler->filter('form[action*="/admin/membres/'.$admin->getId().'/basculer"]')->count());

        // Désactivation d'un membre.
        $formulaire = $crawler->filter('form[action*="/admin/membres/'.$cible->getId().'/basculer"]')->form();
        $client->submit($formulaire);
        $this->assertResponseRedirects('/admin/membres');

        $cibleRafraichie = $client->getContainer()->get(MembreRepository::class)->find($cible->getId());
        $this->assertFalse($cibleRafraichie->isActif(), 'Le compte doit être désactivé.');

        // L'admin se déconnecte pour tester la connexion du membre désactivé.
        $client->request('GET', '/deconnexion');

        $client->request('GET', '/connexion');
        $form = $client->getCrawler()->selectButton('Se connecter')->form([
            'email' => 'cible@campus-eni.fr',
            'password' => 'MotDePasse123',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/connexion');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'désactivé');
    }

    public function testSuppressionAvisParLAdmin(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $passager = self::creerMembre($client, 'passager@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'));
        $participation = self::participerEtTerminer($client, $trajet, $conducteur, $passager);

        // Le passager laisse un avis.
        $client->loginUser($passager);
        $crawler = $client->request('GET', '/participation/'.$participation->getId().'/avis');
        $form = $crawler->selectButton('Publier mon avis')->form([
            'avis[note]' => '2',
            'avis[commentaire]' => 'Conduite trop rapide à mon goût.',
        ]);
        $client->submit($form);

        // L'admin le modère et le supprime.
        $client->loginUser(self::admin($client));
        $crawler = $client->request('GET', '/admin/avis');
        $this->assertSelectorTextContains('body', 'Conduite trop rapide à mon goût.');

        $form = $crawler->selectButton('Supprimer')->form();
        $client->submit($form);
        $this->assertResponseRedirects('/admin/avis');

        $this->assertCount(0, $client->getContainer()->get(AvisRepository::class)->findAll());
    }
}
