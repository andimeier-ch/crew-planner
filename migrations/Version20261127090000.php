<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CR 8: each event can have one leader, chosen in the matrix.
 */
final class Version20261127090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event leader';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event ADD leader_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE event ADD CONSTRAINT FK_3BAE0AA773154ED4 FOREIGN KEY (leader_id) REFERENCES staff (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_3BAE0AA773154ED4 ON event (leader_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE event DROP FOREIGN KEY FK_3BAE0AA773154ED4');
        $this->addSql('DROP INDEX IDX_3BAE0AA773154ED4 ON event');
        $this->addSql('ALTER TABLE event DROP leader_id');
    }
}
