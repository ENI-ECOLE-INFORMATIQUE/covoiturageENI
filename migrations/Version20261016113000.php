<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * M2 : lien entre un trajet et le véhicule du conducteur (nullable —
 * la suppression du véhicule conserve les places offertes du trajet).
 */
final class Version20261016113000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute vehicule_id sur trajet (M2 : publication avec véhicule).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trajet ADD vehicule_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE trajet ADD CONSTRAINT FK_2B5BA98C4A4A3511 FOREIGN KEY (vehicule_id) REFERENCES vehicule (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_2B5BA98C4A4A3511 ON trajet (vehicule_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE trajet DROP FOREIGN KEY FK_2B5BA98C4A4A3511');
        $this->addSql('DROP INDEX IDX_2B5BA98C4A4A3511 ON trajet');
        $this->addSql('ALTER TABLE trajet DROP vehicule_id');
    }
}
