<?php

declare(strict_types=1);

namespace OpenDemat\ExampleBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260527141452 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align the example purchase request table with the vehicle-like workflow model.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE example.demande_achat_interne ADD type_achat VARCHAR(50) DEFAULT \'FOURNITURE\' NOT NULL');
        $this->addSql('ALTER TABLE example.demande_achat_interne ALTER type_achat DROP DEFAULT');
        $this->addSql('ALTER TABLE example.demande_achat_interne ADD attestations JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE example.demande_achat_interne ALTER attestations DROP DEFAULT');
        $this->addSql('ALTER TABLE example.demande_achat_interne ADD statut_traitement VARCHAR(50) DEFAULT \'soumise\' NOT NULL');
        $this->addSql('UPDATE example.demande_achat_interne SET statut_traitement = statut');
        $this->addSql('ALTER TABLE example.demande_achat_interne ALTER statut_traitement DROP DEFAULT');
        $this->addSql('ALTER TABLE example.demande_achat_interne RENAME COLUMN avis_manager TO commentaire_gestionnaire');
        $this->addSql('ALTER TABLE example.demande_achat_interne DROP avis_achats');
        $this->addSql('ALTER TABLE example.demande_achat_interne DROP statut');
        $this->addSql('ALTER TABLE example.demande_achat_interne ALTER date_fin_process TYPE DATE USING date_fin_process::date');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE example.demande_achat_interne ADD statut VARCHAR(50) DEFAULT \'soumise\' NOT NULL');
        $this->addSql('UPDATE example.demande_achat_interne SET statut = statut_traitement');
        $this->addSql('ALTER TABLE example.demande_achat_interne ALTER statut DROP DEFAULT');
        $this->addSql('ALTER TABLE example.demande_achat_interne RENAME COLUMN commentaire_gestionnaire TO avis_manager');
        $this->addSql('ALTER TABLE example.demande_achat_interne ADD avis_achats TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE example.demande_achat_interne DROP type_achat');
        $this->addSql('ALTER TABLE example.demande_achat_interne DROP attestations');
        $this->addSql('ALTER TABLE example.demande_achat_interne DROP statut_traitement');
        $this->addSql('ALTER TABLE example.demande_achat_interne ALTER date_fin_process TYPE TIMESTAMP(0) WITHOUT TIME ZONE');
    }
}
