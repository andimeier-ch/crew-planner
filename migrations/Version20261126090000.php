<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CR 6: predefined event names, suggested when the admin types an event title.
 */
final class Version20261126090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add event_name table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE event_name (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(200) NOT NULL, UNIQUE INDEX UNIQ_41E832AD5E237E06 (name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE event_name');
    }
}
