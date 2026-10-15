<?php

namespace App\Tests\Functional;

use App\Entity\Membre;
use App\Repository\MembreRepository;
use App\Repository\SiteRepository;
use App\Tests\DatabasePrimerTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

final class InscriptionTest extends WebTestCase
{
    use DatabasePrimerTrait;

    private static function donneesValides(AbstractBrowser $client): array
    {
        $site = $client->getContainer()->get(SiteRepository::class)->findOneBy(['actif' => true]);

        return [
            'registration_form[prenom]' => 'Jean',
            'registration_form[nom]' => 'Dupont',
            'registration_form[email]' => 'jean.dupont@campus-eni.fr',
            'registration_form[statut]' => 'etudiant',
            'registration_form[campusRattachement]' => $site->getId(),
            'registration_form[telephone]' => '0612345678',
            'registration_form[plainPassword][first]' => 'MotDePasse123',
            'registration_form[plainPassword][second]' => 'MotDePasse123',
        ];
    }

    private static function inscrire(AbstractBrowser $client, array $donnees): void
    {
        $crawler = $client->request('GET', '/inscription');
        $form = $crawler->selectButton('registration_form[submit]')->form($donnees);
        $client->submit($form);
    }

    public function testInscriptionCreeUnCompteNonVerifie(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());

        $crawler = $client->request('GET', '/inscription');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('registration_form[submit]')->form(self::donneesValides($client));
        $client->submit($form);

        $this->assertResponseRedirects('/attente-verification');

        $membre = $client->getContainer()->get(MembreRepository::class)->findOneBy(['email' => 'jean.dupont@campus-eni.fr']);
        $this->assertNotNull($membre, 'Le membre doit exister en base.');
        $this->assertNull($membre->getEmailVerifieLe(), 'Le compte ne doit pas encore être vérifié.');
        $this->assertSame(['ROLE_USER'], $membre->getRoles());
        $this->assertNotSame('MotDePasse123', $membre->getMotDePasse(), 'Le mot de passe doit être stocké haché.');
    }

    public function testEmailHorsDomaineCampusEniEstRejete(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());

        $donnees = self::donneesValides($client);
        $donnees['registration_form[email]'] = 'jean.dupont@gmail.com';
        self::inscrire($client, $donnees);

        // Depuis Symfony 7.3, un formulaire soumis invalide répond 422.
        $this->assertResponseStatusCodeSame(422);
        $this->assertSelectorTextContains('.invalid-feedback', 'campus-eni.fr');

        $membre = $client->getContainer()->get(MembreRepository::class)->findOneBy(['email' => 'jean.dupont@gmail.com']);
        $this->assertNull($membre, 'Aucun compte ne doit être créé hors du domaine ENI.');
    }

    public function testLoginRefuseUnCompteNonVerifie(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());

        self::inscrire($client, self::donneesValides($client));
        $this->assertResponseRedirects('/attente-verification');

        $client->request('GET', '/connexion');
        $form = $client->getCrawler()->selectButton('Se connecter')->form([
            'email' => 'jean.dupont@campus-eni.fr',
            'password' => 'MotDePasse123',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/connexion');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'vérifiée');
    }

    public function testVerificationEmailPuisConnexion(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());

        self::inscrire($client, self::donneesValides($client));
        $this->assertResponseRedirects('/attente-verification');

        $membre = $client->getContainer()->get(MembreRepository::class)->findOneBy(['email' => 'jean.dupont@campus-eni.fr']);
        $this->assertNotNull($membre);

        $helper = $client->getContainer()->get(VerifyEmailHelperInterface::class);
        $signature = $helper->generateSignature(
            'app_verify_email',
            (string) $membre->getId(),
            $membre->getEmail(),
            ['id' => $membre->getId()]
        );

        $client->request('GET', $signature->getSignedUrl());
        $this->assertResponseRedirects('/connexion');

        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-success', 'vérifiée');

        $membreRafraichi = $client->getContainer()->get(MembreRepository::class)->find($membre->getId());
        $this->assertNotNull($membreRafraichi->getEmailVerifieLe(), 'Le compte doit être vérifié.');

        $form = $client->getCrawler()->selectButton('Se connecter')->form([
            'email' => 'jean.dupont@campus-eni.fr',
            'password' => 'MotDePasse123',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->assertSelectorTextContains('nav.navbar', 'Déconnexion');
    }
}
