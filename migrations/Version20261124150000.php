<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CR 7: split the staff name into first and last name.
 * Existing names are split at the first space; single-word names get an empty last name,
 * which the admin corrects afterwards (flagged in the staff list).
 */
final class Version20261124150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Split staff name into first_name and last_name';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE staff ADD first_name VARCHAR(100) NOT NULL DEFAULT '', ADD last_name VARCHAR(100) NOT NULL DEFAULT ''");
        $this->addSql("UPDATE staff SET
            first_name = SUBSTRING_INDEX(TRIM(name), ' ', 1),
            last_name = IF(LOCATE(' ', TRIM(name)) > 0, TRIM(SUBSTRING(TRIM(name), LOCATE(' ', TRIM(name)) + 1)), '')");
        $this->addSql('ALTER TABLE staff DROP name, ALTER first_name DROP DEFAULT, ALTER last_name DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE staff ADD name VARCHAR(150) NOT NULL DEFAULT ''");
        $this->addSql("UPDATE staff SET name = TRIM(CONCAT_WS(' ', first_name, last_name))");
        $this->addSql('ALTER TABLE staff DROP first_name, DROP last_name, ALTER name DROP DEFAULT');
    }
}
