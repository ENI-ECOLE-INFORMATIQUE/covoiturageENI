<?php

namespace App\DataFixtures;

use App\Entity\Membre;
use App\Entity\Participation;
use App\Entity\Site;
use App\Entity\Trajet;
use App\Entity\Vehicule;
use App\Enum\SensTrajet;
use App\Enum\StatutMembre;
use App\Enum\StatutParticipation;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $campus = [];
        foreach (['ENI Quimper', 'ENI Rennes', 'ENI Nantes', 'ENI CEL'] as $nom) {
            $site = new Site();
            $site->setNom($nom);
            $manager->persist($site);
            $campus[$nom] = $site;
        }

        $admin = new Membre();
        $admin->setPrenom('Compte');
        $admin->setNom('Administrateur');
        $admin->setEmail('admin@campus-eni.fr');
        $admin->setStatut(StatutMembre::Personnel);
        $admin->setCampusRattachement($campus['ENI Rennes']);
        $admin->setEmailVerifieLe(new \DateTimeImmutable());
        $admin->setRoles(['ROLE_USER', 'ROLE_ADMIN']);
        $admin->setMotDePasse($this->passwordHasher->hashPassword($admin, 'admin1234'));
        $manager->persist($admin);

        // Données de démonstration pour le jalon M2.
        $conducteur = new Membre();
        $conducteur->setPrenom('Camille');
        $conducteur->setNom('Le Goff');
        $conducteur->setEmail('camille.legoff@campus-eni.fr');
        $conducteur->setStatut(StatutMembre::Etudiant);
        $conducteur->setCampusRattachement($campus['ENI Rennes']);
        $conducteur->setEmailVerifieLe(new \DateTimeImmutable());
        $conducteur->setMotDePasse($this->passwordHasher->hashPassword($conducteur, 'MotDePasse123'));
        $manager->persist($conducteur);

        $passager = new Membre();
        $passager->setPrenom('Hugo');
        $passager->setNom('Martin');
        $passager->setEmail('hugo.martin@campus-eni.fr');
        $passager->setStatut(StatutMembre::Formateur);
        $passager->setCampusRattachement($campus['ENI Nantes']);
        $passager->setEmailVerifieLe(new \DateTimeImmutable());
        $passager->setMotDePasse($this->passwordHasher->hashPassword($passager, 'MotDePasse123'));
        $manager->persist($passager);

        $vehicule = new Vehicule();
        $vehicule->setMembre($conducteur);
        $vehicule->setMarque('Peugeot');
        $vehicule->setModele('208');
        $vehicule->setCouleur('Gris');
        $vehicule->setPlaque('AB-123-CD');
        $vehicule->setNbPlaces(3);
        $vehicule->setAnimaux(true);
        $manager->persist($vehicule);

        $demainMatin = (new \DateTimeImmutable('tomorrow'))->setTime(8, 0);
        $demainSoir = (new \DateTimeImmutable('tomorrow'))->setTime(17, 30);

        $trajetAller = new Trajet();
        $trajetAller->setConducteur($conducteur);
        $trajetAller->setVehicule($vehicule);
        $trajetAller->setSite($campus['ENI Rennes']);
        $trajetAller->setSens(SensTrajet::VersSite);
        $trajetAller->setVilleDepart('Vannes');
        $trajetAller->setVilleArrivee('Rennes');
        $trajetAller->setAdresseRendezVous('Gare de Vannes');
        $trajetAller->setDateDepart($demainMatin);
        $trajetAller->setDureeEstimee(75);
        $trajetAller->setPrix('6.00');
        $trajetAller->setNbPlacesOffertes($vehicule->getNbPlaces());
        $manager->persist($trajetAller);

        $trajetRetour = new Trajet();
        $trajetRetour->setConducteur($conducteur);
        $trajetRetour->setVehicule($vehicule);
        $trajetRetour->setSite($campus['ENI Rennes']);
        $trajetRetour->setSens(SensTrajet::DepuisSite);
        $trajetRetour->setVilleDepart('Rennes');
        $trajetRetour->setVilleArrivee('Vannes');
        $trajetRetour->setDateDepart($demainSoir);
        $trajetRetour->setPrix('6.00');
        $trajetRetour->setNbPlacesOffertes($vehicule->getNbPlaces());
        $manager->persist($trajetRetour);

        // Une demande en attente pour démontrer la modération par le conducteur.
        $participation = new Participation();
        $participation->setTrajet($trajetAller);
        $participation->setMembre($passager);
        $participation->setNbPlaces(1);
        $participation->setStatut(StatutParticipation::EnAttente);
        $manager->persist($participation);

        $manager->flush();
    }
}
