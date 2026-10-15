<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261015084924 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE avis (id INT AUTO_INCREMENT NOT NULL, note INT NOT NULL, commentaire LONGTEXT DEFAULT NULL, cree_le DATETIME NOT NULL, auteur_id INT NOT NULL, cible_id INT NOT NULL, participation_id INT NOT NULL, UNIQUE INDEX UNIQ_8F91ABF06ACE3B73 (participation_id), INDEX IDX_8F91ABF060BB6FE6 (auteur_id), INDEX IDX_8F91ABF0A96E5E09 (cible_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE membre (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, nom VARCHAR(80) NOT NULL, prenom VARCHAR(80) NOT NULL, telephone VARCHAR(20) DEFAULT NULL, statut VARCHAR(20) NOT NULL, actif TINYINT DEFAULT 1 NOT NULL, email_verifie_le DATETIME DEFAULT NULL, roles JSON NOT NULL, campus_rattachement_id INT NOT NULL, UNIQUE INDEX UNIQ_F6B4FB29E7927C74 (email), INDEX IDX_F6B4FB294DD35D89 (campus_rattachement_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE participation (id INT AUTO_INCREMENT NOT NULL, nb_places INT DEFAULT 1 NOT NULL, statut VARCHAR(15) NOT NULL, cree_le DATETIME NOT NULL, trajet_id INT NOT NULL, membre_id INT NOT NULL, UNIQUE INDEX uq_participation_trajet_membre (trajet_id, membre_id), INDEX IDX_AB55E24FD12A823 (trajet_id), INDEX IDX_AB55E24F6A99F74A (membre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE site (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(80) NOT NULL, actif TINYINT DEFAULT 1 NOT NULL, adresse VARCHAR(255) DEFAULT NULL, ville VARCHAR(80) DEFAULT NULL, code_postal VARCHAR(10) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE trajet (id INT AUTO_INCREMENT NOT NULL, sens VARCHAR(20) NOT NULL, ville_depart VARCHAR(120) NOT NULL, ville_arrivee VARCHAR(120) NOT NULL, adresse_rendez_vous VARCHAR(255) DEFAULT NULL, date_depart DATETIME NOT NULL, duree_estimee INT DEFAULT NULL, prix NUMERIC(5, 2) DEFAULT \'0.00\' NOT NULL, nb_places_offertes INT NOT NULL, statut VARCHAR(10) NOT NULL, commentaire LONGTEXT DEFAULT NULL, conducteur_id INT NOT NULL, site_id INT NOT NULL, INDEX idx_trajet_recherche (site_id, sens, date_depart), INDEX IDX_2B5BA98CF16F4AC6 (conducteur_id), INDEX IDX_2B5BA98CF6BD1646 (site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE vehicule (id INT AUTO_INCREMENT NOT NULL, marque VARCHAR(80) NOT NULL, modele VARCHAR(80) NOT NULL, couleur VARCHAR(40) DEFAULT NULL, nb_places INT NOT NULL, plaque VARCHAR(15) NOT NULL, fumeur TINYINT DEFAULT 0 NOT NULL, animaux TINYINT DEFAULT 0 NOT NULL, membre_id INT NOT NULL, INDEX IDX_292FFF1D6A99F74A (membre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT FK_8F91ABF060BB6FE6 FOREIGN KEY (auteur_id) REFERENCES membre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT FK_8F91ABF0A96E5E09 FOREIGN KEY (cible_id) REFERENCES membre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE avis ADD CONSTRAINT FK_8F91ABF06ACE3B73 FOREIGN KEY (participation_id) REFERENCES participation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE membre ADD CONSTRAINT FK_F6B4FB294DD35D89 FOREIGN KEY (campus_rattachement_id) REFERENCES site (id)');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24FD12A823 FOREIGN KEY (trajet_id) REFERENCES trajet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE participation ADD CONSTRAINT FK_AB55E24F6A99F74A FOREIGN KEY (membre_id) REFERENCES membre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trajet ADD CONSTRAINT FK_2B5BA98CF16F4AC6 FOREIGN KEY (conducteur_id) REFERENCES membre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE trajet ADD CONSTRAINT FK_2B5BA98CF6BD1646 FOREIGN KEY (site_id) REFERENCES site (id)');
        $this->addSql('ALTER TABLE vehicule ADD CONSTRAINT FK_292FFF1D6A99F74A FOREIGN KEY (membre_id) REFERENCES membre (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY FK_8F91ABF060BB6FE6');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY FK_8F91ABF0A96E5E09');
        $this->addSql('ALTER TABLE avis DROP FOREIGN KEY FK_8F91ABF06ACE3B73');
        $this->addSql('ALTER TABLE membre DROP FOREIGN KEY FK_F6B4FB294DD35D89');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24FD12A823');
        $this->addSql('ALTER TABLE participation DROP FOREIGN KEY FK_AB55E24F6A99F74A');
        $this->addSql('ALTER TABLE trajet DROP FOREIGN KEY FK_2B5BA98CF16F4AC6');
        $this->addSql('ALTER TABLE trajet DROP FOREIGN KEY FK_2B5BA98CF6BD1646');
        $this->addSql('ALTER TABLE vehicule DROP FOREIGN KEY FK_292FFF1D6A99F74A');
        $this->addSql('DROP TABLE avis');
        $this->addSql('DROP TABLE membre');
        $this->addSql('DROP TABLE participation');
        $this->addSql('DROP TABLE site');
        $this->addSql('DROP TABLE trajet');
        $this->addSql('DROP TABLE vehicule');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
