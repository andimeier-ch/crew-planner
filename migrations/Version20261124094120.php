<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CR 2: surveys are closed manually instead of locking at the deadline.
 * Surveys whose deadline has already passed are closed, so nothing reopens for participants on deploy.
 */
final class Version20261124094120 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add survey status (open/closed); close surveys whose deadline has passed';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE survey ADD status VARCHAR(20) NOT NULL DEFAULT 'open'");
        $this->addSql("UPDATE survey SET status = 'closed' WHERE deadline < NOW()");
        $this->addSql('ALTER TABLE survey ALTER status DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE survey DROP status');
    }
}
