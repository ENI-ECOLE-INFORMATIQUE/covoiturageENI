<?php

namespace App\Tests\Functional;

use App\Entity\Membre;
use App\Entity\Participation;
use App\Entity\Site;
use App\Entity\Trajet;
use App\Entity\Vehicule;
use App\Enum\SensTrajet;
use App\Enum\StatutMembre;
use App\Enum\StatutParticipation;
use App\Enum\StatutTrajet;
use App\Repository\ParticipationRepository;
use App\Repository\TrajetRepository;
use App\Tests\DatabasePrimerTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class TrajetTest extends WebTestCase
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

    private static function participationRepository(KernelBrowser $client): ParticipationRepository
    {
        return $client->getContainer()->get(ParticipationRepository::class);
    }

    /**
     * Réserve via le formulaire de la page détail (parcours HTTP réel).
     */
    private static function reserverViaUi(KernelBrowser $client, Trajet $trajet, Membre $passager): void
    {
        $client->loginUser($passager);
        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Demander à participer')->form([
            'reservation[nbPlaces]' => '1',
        ]);
        $client->submit($form);
    }

    /**
     * Publication complète : création du véhicule puis du trajet, visible en recherche.
     */
    public function testPublicationDeVehiculePuisTrajet(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $client->loginUser($conducteur);

        $crawler = $client->request('GET', '/vehicule/nouveau');
        $form = $crawler->selectButton('Enregistrer le véhicule')->form([
            'vehicule[marque]' => 'Peugeot',
            'vehicule[modele]' => '208',
            'vehicule[plaque]' => 'AB-123-CD',
            'vehicule[nbPlaces]' => '3',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/vehicules');

        $crawler = $client->request('GET', '/trajet/nouveau');
        $this->assertResponseIsSuccessful();

        $vehicule = self::em($client)->getRepository(Vehicule::class)->findOneBy(['membre' => $conducteur]);
        $site = self::em($client)->getRepository(Site::class)->findOneBy(['actif' => true]);

        $form = $crawler->selectButton('Publier le trajet')->form([
            'trajet[vehicule]' => $vehicule->getId(),
            'trajet[site]' => $site->getId(),
            'trajet[sens]' => 'vers_site',
            'trajet[villeDepart]' => 'Vannes',
            'trajet[villeArrivee]' => 'Rennes',
            'trajet[dateDepart]' => (new \DateTimeImmutable('+2 days'))->format('Y-m-d\TH:00'),
            'trajet[prix]' => '6.50',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects();
        $client->followRedirect();

        $trajet = $client->getContainer()->get(TrajetRepository::class)->findOneBy(['villeDepart' => 'Vannes']);
        $this->assertNotNull($trajet, 'Le trajet doit exister.');
        $this->assertSame(3, $trajet->getNbPlacesOffertes(), 'Les places offertes suivent le véhicule.');
        $this->assertSame(StatutTrajet::Ouvert, $trajet->getStatut());

        $client->request('GET', '/');
        $this->assertSelectorTextContains('body', 'Vannes');
    }

    public function testRechercheFiltreParCampusEtVille(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'));

        $site = self::em($client)->getRepository(Site::class)->findOneBy(['actif' => true]);

        $crawler = $client->request('GET', '/');
        $form = $crawler->selectButton('Rechercher')->form([
            'trajet_search[site]' => $site->getId(),
            'trajet_search[ville]' => 'Vannes',
        ]);
        $client->submit($form);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Vannes');

        // Un filtre sur une ville inconnue ne renvoie rien.
        $crawler = $client->request('GET', '/');
        $form = $crawler->selectButton('Rechercher')->form(['trajet_search[ville]' => 'Brest']);
        $client->submit($form);
        $this->assertSelectorTextContains('body', 'Aucun trajet');
    }

    public function testUnTrajetPasseNapparaPasPasDansLaRecherche(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('-1 day'));
        $trajet->setVilleDepart('Lorient');
        self::em($client)->flush();

        $client->request('GET', '/');

        $this->assertSelectorTextNotContains('body', 'Lorient');
    }

    /**
     * Règle métier 1 : le conducteur ne peut pas réserver son propre trajet.
     */
    public function testRegle1ConducteurNePeutPasReserverSonTrajet(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'));
        $client->loginUser($conducteur);

        $client->request('POST', '/trajet/'.$trajet->getId().'/reserver', [
            'reservation' => ['nbPlaces' => '1'],
        ]);
        $client->followRedirect();

        $this->assertSelectorTextContains('.alert-danger', 'propre trajet');
        $this->assertCount(0, self::participationRepository($client)->findBy(['trajet' => $trajet]));
    }

    /**
     * Règle métier 5 : réservation impossible dans les 2 heures avant le départ.
     */
    public function testRegle5ReservationTropTardiveRefusee(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $passager = self::creerMembre($client, 'passager@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, (new \DateTimeImmutable('+90 minutes')));
        $client->loginUser($passager);

        $client->request('POST', '/trajet/'.$trajet->getId().'/reserver', [
            'reservation' => ['nbPlaces' => '1'],
        ]);
        $client->followRedirect();

        $this->assertSelectorTextContains('.alert-danger', 'avant le départ');
        $this->assertCount(0, self::participationRepository($client)->findBy(['trajet' => $trajet]));
    }

    /**
     * Règle métier 2 : parcours complet réservation → acceptation → trajet complet
     * puis nouvelle réservation refusée.
     */
    public function testRegle2ReservationAcceptationPuisTrajetComplet(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $passager1 = self::creerMembre($client, 'passager1@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'), 1);
        $client->loginUser($passager1);

        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Demander à participer')->form([
            'reservation[nbPlaces]' => '1',
        ]);
        $client->submit($form);
        $client->followRedirect();

        $participation = self::participationRepository($client)->findOneBy(['trajet' => $trajet, 'membre' => $passager1]);
        $this->assertNotNull($participation);
        $this->assertSame(StatutParticipation::EnAttente, $participation->getStatut());

        // Le conducteur accepte.
        $client->loginUser($conducteur);
        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Accepter')->form();
        $client->submit($form);
        $client->followRedirect();

        $participation = self::participationRepository($client)->find($participation->getId());
        $this->assertSame(StatutParticipation::Accepte, $participation->getStatut());

        $trajetRafraichi = $client->getContainer()->get(TrajetRepository::class)->find($trajet->getId());
        $this->assertSame(StatutTrajet::Complet, $trajetRafraichi->getStatut(), 'Un trajet dont les places sont prises passe complet.');

        // Le trajet complet n'accepte plus de réservation.
        $passager2 = self::creerMembre($client, 'passager2@campus-eni.fr');
        $client->loginUser($passager2);
        $client->request('POST', '/trajet/'.$trajet->getId().'/reserver', [
            'reservation' => ['nbPlaces' => '1'],
        ]);
        $client->followRedirect();

        $this->assertSelectorTextContains('.alert-danger', 'plus de réservation');
        $this->assertCount(1, self::participationRepository($client)->findBy(['trajet' => $trajet]));
    }

    /**
     * Règle métier 2 : l'acceptation ne peut pas dépasser les places offertes.
     */
    public function testRegle2AcceptationAuDelaDesPlacesRefusee(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $p1 = self::creerMembre($client, 'p1@campus-eni.fr');
        $p2 = self::creerMembre($client, 'p2@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'), 1);

        self::reserverViaUi($client, $trajet, $p1);
        self::reserverViaUi($client, $trajet, $p2);

        $participation1 = self::participationRepository($client)->findOneBy(['trajet' => $trajet, 'membre' => $p1]);
        $participation2 = self::participationRepository($client)->findOneBy(['trajet' => $trajet, 'membre' => $p2]);
        $this->assertNotNull($participation1);
        $this->assertNotNull($participation2);

        $client->loginUser($conducteur);
        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Accepter')->form();
        $client->submit($form); // accepte la première participation
        $client->followRedirect();

        // Tenter d'accepter la seconde demande alors qu'il n'y a plus de place.
        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Accepter')->form();
        $client->submit($form);
        $client->followRedirect();

        $this->assertSelectorTextContains('.alert-danger', 'insuffisantes');
        $this->assertSame(StatutParticipation::EnAttente, self::participationRepository($client)->find($participation2->getId())->getStatut());
        $this->assertSame(StatutParticipation::Accepte, self::participationRepository($client)->find($participation1->getId())->getStatut());
    }

    /**
     * Règle métier 3 : le conducteur peut refuser une réservation.
     */
    public function testRegle3RefusParLeConducteur(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $p1 = self::creerMembre($client, 'p1@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'));

        self::reserverViaUi($client, $trajet, $p1);
        $participation = self::participationRepository($client)->findOneBy(['trajet' => $trajet, 'membre' => $p1]);
        $this->assertNotNull($participation);

        $client->loginUser($conducteur);
        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Refuser')->form();
        $client->submit($form);
        $client->followRedirect();

        $this->assertSame(
            StatutParticipation::Refuse,
            self::participationRepository($client)->find($participation->getId())->getStatut()
        );
    }

    /**
     * Règle métier 4 : l'annulation du trajet annule toutes les participations.
     */
    public function testRegle4AnnulationDuTrajetAnnuleLesParticipations(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $p1 = self::creerMembre($client, 'p1@campus-eni.fr');
        $p2 = self::creerMembre($client, 'p2@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'));

        self::reserverViaUi($client, $trajet, $p1);
        self::reserverViaUi($client, $trajet, $p2);

        $participation1 = self::participationRepository($client)->findOneBy(['trajet' => $trajet, 'membre' => $p1]);
        $participation2 = self::participationRepository($client)->findOneBy(['trajet' => $trajet, 'membre' => $p2]);
        $this->assertNotNull($participation1);
        $this->assertNotNull($participation2);

        $client->loginUser($conducteur);
        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Annuler le trajet')->form();
        $client->submit($form);
        $client->followRedirect();

        $trajetRafraichi = $client->getContainer()->get(TrajetRepository::class)->find($trajet->getId());
        $this->assertSame(StatutTrajet::Annule, $trajetRafraichi->getStatut());
        $this->assertSame(StatutParticipation::Annule, self::participationRepository($client)->find($participation1->getId())->getStatut());
        $this->assertSame(StatutParticipation::Annule, self::participationRepository($client)->find($participation2->getId())->getStatut());
    }

    public function testPassagerAnnuleSaParticipation(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $conducteur = self::creerMembre($client, 'conducteur@campus-eni.fr');
        $passager = self::creerMembre($client, 'p1@campus-eni.fr');
        $trajet = self::creerTrajet($client, $conducteur, new \DateTimeImmutable('+1 day'));

        self::reserverViaUi($client, $trajet, $passager);
        $participation = self::participationRepository($client)->findOneBy(['trajet' => $trajet, 'membre' => $passager]);
        $this->assertNotNull($participation);

        $crawler = $client->request('GET', '/trajet/'.$trajet->getId());
        $form = $crawler->selectButton('Annuler ma participation')->form();
        $client->submit($form);
        $client->followRedirect();

        $this->assertSame(
            StatutParticipation::Annule,
            self::participationRepository($client)->find($participation->getId())->getStatut()
        );
    }

    public function testPublicationInterditeSansVehicule(): void
    {
        $client = static::createClient();
        self::preparerBase($client->getContainer());
        $membre = self::creerMembre($client, 'nouveau@campus-eni.fr');
        $client->loginUser($membre);

        $client->request('GET', '/trajet/nouveau');

        $this->assertResponseRedirects('/vehicule/nouveau');
    }
}
