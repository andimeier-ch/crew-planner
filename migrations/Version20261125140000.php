<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CR 5: reminder e-mails. Surveys get their own reminder subject and body; existing surveys get the default text.
 */
final class Version20261125140000 extends AbstractMigration
{
    private const DEFAULT_SUBJECT = 'Erinnerung: {surveyTitle}';

    private const DEFAULT_BODY = <<<'TEXT'
        Hallo {firstName}

        Von dir fehlt noch die Antwort zur Umfrage «{surveyTitle}». Bitte trage so bald wie möglich ein, an welchen Daten du verfügbar bist (Deadline: {deadline}):

        {surveyLink}

        Vielen Dank!
        Musik FEG Langenthal
        TEXT;

    public function getDescription(): string
    {
        return 'Add survey reminder subject/body';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE survey ADD reminder_subject VARCHAR(255) NOT NULL DEFAULT '', ADD reminder_body LONGTEXT NOT NULL");
        $this->addSql(
            'UPDATE survey SET reminder_subject = :subject, reminder_body = :body',
            ['subject' => self::DEFAULT_SUBJECT, 'body' => self::DEFAULT_BODY],
        );
        $this->addSql('ALTER TABLE survey ALTER reminder_subject DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE survey DROP reminder_subject, DROP reminder_body');
    }
}
