<?php

declare(strict_types=1);

namespace OpenDemat\ExampleBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260527142838 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add author and correction tracking fields to the example purchase request process.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE example.demande_achat_interne ADD motif_correction TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE example.demande_achat_interne ADD motif_refus TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE example.demande_achat_interne ADD champs_a_corriger JSON DEFAULT \'[]\' NOT NULL');
        $this->addSql('ALTER TABLE example.demande_achat_interne ADD auteur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE example.demande_achat_interne ADD CONSTRAINT FK_A481043F60BB6FE6 FOREIGN KEY (auteur_id) REFERENCES "user" (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX IDX_A481043F60BB6FE6 ON example.demande_achat_interne (auteur_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE example.demande_achat_interne DROP CONSTRAINT FK_A481043F60BB6FE6');
        $this->addSql('DROP INDEX example.IDX_A481043F60BB6FE6');
        $this->addSql('ALTER TABLE example.demande_achat_interne DROP motif_correction');
        $this->addSql('ALTER TABLE example.demande_achat_interne DROP motif_refus');
        $this->addSql('ALTER TABLE example.demande_achat_interne DROP champs_a_corriger');
        $this->addSql('ALTER TABLE example.demande_achat_interne DROP auteur_id');
    }
}
