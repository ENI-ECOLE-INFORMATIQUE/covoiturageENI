<?php

namespace App\Tests\Functional;

use App\Entity\Avis;
use App\Entity\Membre;
use App\Entity\Participation;
use App\Entity\Site;
use App\Entity\Trajet;
use App\Entity\Vehicule;
use App\Enum\SensTrajet;
use App\Enum\StatutMembre;
use App\Enum\StatutTrajet;
use App\Repository\AvisRepository;
use App\Repository\ParticipationRepository;
use App\Tests\DatabasePrimerTrait;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * M3 : règle métier 6 (avis), profils publics, notifications en file.
 */
final class AvisTest extends WebTestCase
{
    use DatabasePrimerTrait;

    private static function em(KernelBrowser $client): EntityManagerInterface
    {
        return $client->getContainer()->get('doctrine')->getManager();
    }

    private static function creerMembre(KernelBrowser $client, string $email): Membre
    {
        $membre = new Membre();
        $membre->setEmail($email);
        $membre->setPrenom('Test');
        $membre->setNom(ucfirst(substr($email, 0, strpos($email, '@'))));
        $membre->setStatut(StatutMembre::Etudiant);
        $membre->setCampusRattachement(self::em($client)->getRepository(Site::class)->findOneBy(['actif' => true]));
        $membre->setEmailVerifieLe(new \DateTimeImmutable());
        $membre->setMotDePasse(
            $client->getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($membre, 'MotDePasse123')
        );

        self::em($client)->persist($membre);
        self::em($client)->flush();

        return $membre;
    }

    private static function creerTrajet(KernelBrowser $client, Membre $conducteur, \DateTimeImmutable $depart, int $places = 2): Trajet
    {
        $entityManager = self::em($client);

        $vehicule = new Vehicule();
        $vehicule->setMembre($conducteur);
        $vehicule->setMarque('Renault');
        $vehicule->setModele('Clio');
        $vehicule->setPlaque('ZZ-987-WV');
        $vehicule->setNbPlaces($places);
        $entityManager->persist($vehicule);

        $trajet = new Trajet();
        $trajet->setConducteur($conducteur);
        $trajet->setVehicule($vehicule);
        $trajet->setSite($entityManager->getRepository(Site::class)->findOneBy(['actif' => true]));
        $trajet->setSens(SensTrajet::VersSite);
        $trajet->setVilleDepart('Vannes');
        $trajet->setVilleArrivee('Rennes');
        $trajet->setDateDepart($depart);
        $trajet->setPrix('5.00');
        $trajet->setNbPlacesOffertes($places);
        $trajet->setStatut(StatutTrajet::Ouvert);
        $entityManager->persist($trajet);

        $entityManager->flush();

        return $trajet;
    }

    private static function reserverViaUi(KernelBrowser $client, Trajet $trajet, Membre $passager): void
    {
        $client->loginUser($passager);
        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Demander à participer')->form([
            'reservation[nbPlaces]' => '1',
        ]);
        $client->submit($form);
    }

    private static function accepterViaUi(KernelBrowser $client, Trajet $trajet, Membre $conducteur): void
    {
        $client->loginUser($conducteur);
        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Accepter')->form();
        $client->submit($form);
    }

    private static function refuserViaUi(KernelBrowser $client, Trajet $trajet, Membre $conducteur): void
    {
        $client->loginUser($conducteur);
        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Refuser')->form();
        $client->submit($form);
    }

    /**
     * Réserve et fait accepter la participation, puis fait partir le trajet
     * (décalé à il y a 2 heures) pour rendre l'avis possible (règle 6).
     */
    private static function participerEtTerminer(KernelBrowser $client, Trajet $trajet, Membre $conducteur, Membre $passager): Participation
    {
        self::reserverViaUi($client, $trajet, $passager);
        self::accepterViaUi($client, $trajet, $conducteur);

        $participation = $client->getContainer()->get(ParticipationRepository::class)
            ->findOneBy(['trajet' => $trajet, 'membre' => $passager]);
        \assert(null !== $participation);

        // Le trajet part : on décale son départ dans le passé.
        self::fairePartirTrajet($client, $trajet);

        return $participation;
    }

    private static function nbMessages(KernelBrowser $client): int
    {
        /** @var Connection $connection */
        $connection = $client->getContainer()->get('doctrine')->getConnection();

        return (int) $connection->fetchOne('SELECT COUNT(*) FROM messenger_messages');
    }

    /**
     * Décale le départ d'un trajet dans le passé, en SQL direct :
     * après des requêtes HTTP, le conteneur du client est celui du dernier
     * kernel rebooté et son EntityManager ne gère plus les entités créées
     * avant les requêtes ; un UPDATE SQL garantit le commit.
     */
    private static function fairePartirTrajet(KernelBrowser $client, Trajet $trajet): void
    {
        /** @var Connection $connection */
        $connection = $client->getContainer()->get('doctrine')->getConnection();
        $connection->executeStatement(
            'UPDATE trajet SET date_depart = ? WHERE id = ?',
            [(new \DateTimeImmutable('-2 hours'))->format('Y-m-d H:i:s'), $trajet->getId()]
        );
    }

    /**
     * Règle métier 6 : avis possible après le départ, une seule fois.
     */
    public function testAvisApresDepartUneSeuleFois(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $passager = self::creerMembre($client, 'passager@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'));
        $participation = self::participerEtTerminer($client, $trajet, $conducteur, $passager);

        // Le passager voit le bouton sur ses trajets.
        $client->loginUser($passager);
        $client->request('GET', '/mes-trajets');
        $this->assertSelectorTextContains('body', 'Laisser un avis');

        // Il laisse un avis.
        $crawler = $client->request('GET', '/participation/'.$participation->getId().'/avis');
        $form = $crawler->selectButton('Publier mon avis')->form([
            'avis[note]' => '5',
            'avis[commentaire]' => 'Très bon trajet, conducteur ponctuel.',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects();
        $client->followRedirect();

        $avis = $client->getContainer()->get(AvisRepository::class)->findOneBy(['participation' => $participation]);
        $this->assertNotNull($avis, 'L\'avis doit être enregistré.');
        $this->assertSame(5, $avis->getNote());
        $this->assertSame($passager->getId(), $avis->getAuteur()->getId());
        $this->assertSame($conducteur->getId(), $avis->getCible()->getId(), 'La cible est le conducteur.');

        // Une seconde tentative est refusée (règle 6 : une seule fois par participation).
        $client->request('GET', '/participation/'.$participation->getId().'/avis');
        $this->assertResponseRedirects('/mes-trajets');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-warning', 'déjà');

        $client->request('GET', '/mes-trajets');
        $this->assertSelectorTextContains('body', 'Avis envoyé');
        $this->assertSelectorTextNotContains('body', 'Laisser un avis');
    }

    /**
     * Règle métier 6 : aucun avis avant le départ + 1 h.
     */
    public function testAvisRefuseAvantLeDelai(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $passager = self::creerMembre($client, 'passager@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+2 days'));

        self::reserverViaUi($client, $trajet, $passager);
        self::accepterViaUi($client, $trajet, $conducteur);

        $participation = $client->getContainer()->get(ParticipationRepository::class)
            ->findOneBy(['trajet' => $trajet, 'membre' => $passager]);
        \assert(null !== $participation);

        $client->loginUser($passager);
        $client->request('GET', '/participation/'.$participation->getId().'/avis');
        $this->assertResponseRedirects('/mes-trajets');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-warning', 'après le départ');

        $this->assertCount(0, $client->getContainer()->get(AvisRepository::class)->findAll());
    }

    /**
     * Règle métier 6 : aucun avis si la participation n'est pas acceptée.
     */
    public function testAvisRefuseSiParticipationNonAcceptee(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $passager = self::creerMembre($client, 'passager@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'));

        self::reserverViaUi($client, $trajet, $passager); // reste en attente

        $participation = $client->getContainer()->get(ParticipationRepository::class)
            ->findOneBy(['trajet' => $trajet, 'membre' => $passager]);
        \assert(null !== $participation);

        self::fairePartirTrajet($client, $trajet);

        $client->loginUser($passager);
        $client->request('GET', '/participation/'.$participation->getId().'/avis');
        $this->assertResponseRedirects('/mes-trajets');

        $this->assertCount(0, $client->getContainer()->get(AvisRepository::class)->findAll());
    }

    /**
     * Le conducteur peut noter le passager (cible = passager).
     */
    public function testConducteurNoteLePassager(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $passager = self::creerMembre($client, 'passager@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'));
        $participation = self::participerEtTerminer($client, $trajet, $conducteur, $passager);

        $client->loginUser($conducteur);
        $crawler = $client->request('GET', '/participation/'.$participation->getId().'/avis');
        $form = $crawler->selectButton('Publier mon avis')->form([
            'avis[note]' => '4',
            'avis[commentaire]' => 'Passagère ponctuelle et sympa.',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects();
        $avis = $client->getContainer()->get(AvisRepository::class)->findOneBy(['participation' => $participation]);
        $this->assertNotNull($avis);
        $this->assertSame($conducteur->getId(), $avis->getAuteur()->getId());
        $this->assertSame($passager->getId(), $avis->getCible()->getId(), 'La cible est le passager.');
    }

    /**
     * Profil public : moyenne des notes et avis visibles.
     */
    public function testProfilAfficheMoyenneEtAvis(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $passager = self::creerMembre($client, 'passager@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'));
        $participation = self::participerEtTerminer($client, $trajet, $conducteur, $passager);

        $client->loginUser($passager);
        $crawler = $client->request('GET', '/participation/'.$participation->getId().'/avis');
        $form = $crawler->selectButton('Publier mon avis')->form([
            'avis[note]' => '4',
            'avis[commentaire]' => 'Conduite prudente, je recommande.',
        ]);
        $client->submit($form);

        $client->request('GET', '/membre/'.$conducteur->getId());

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', '4,0 / 5');
        $this->assertSelectorTextContains('body', 'Conduite prudente, je recommande.');
        $this->assertSelectorTextContains('body', $passager->getNom());
    }

    /**
     * Règles 3 et 4 : chaque événement met un email en file (transport Doctrine).
     */
    public function testNotificationsEnFile(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $p1 = self::creerMembre($client, 'p1@campus-eni.fr');
        $p2 = self::creerMembre($client, 'p2@campus-eni.fr');
        $trajet1 = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'));
        $trajet2 = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+2 days'));

        self::reserverViaUi($client, $trajet1, $p1);   // 1 message : demande au conducteur
        $this->assertSame(1, self::nbMessages($client));

        self::accepterViaUi($client, $trajet1, $conducteur); // 2 : acceptation au passager
        $this->assertSame(2, self::nbMessages($client));

        self::reserverViaUi($client, $trajet2, $p2);   // 3 : demande
        self::refuserViaUi($client, $trajet2, $conducteur);  // 4 : refus au passager
        $this->assertSame(4, self::nbMessages($client));

        // Règle 4 : annulation du trajet 1 → un email par participant annulé.
        $client->loginUser($conducteur);
        $crawler = $client->request('GET', '/trajet/'.$trajet1->getId());
        $form = $crawler->selectButton('Annuler le trajet')->form();
        $client->submit($form);
        $this->assertSame(5, self::nbMessages($client));
    }
}
