<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CR 4: invitation e-mails sent from the app.
 * - Surveys get their own invitation subject and body; existing surveys get the default template.
 * - Participants remember when they were last e-mailed.
 * - New surveys start as 'draft' (status column is a VARCHAR, so no schema change is needed for that).
 */
final class Version20261125090000 extends AbstractMigration
{
    private const DEFAULT_SUBJECT = 'Umfrage: {surveyTitle}';

    private const DEFAULT_BODY = <<<'TEXT'
        Hallo {firstName}

        Bitte trage bis am {deadline} ein, an welchen Daten du für «{surveyTitle}» verfügbar bist:

        {surveyLink}

        Der Link ist persönlich – bitte nicht weiterleiten.

        Vielen Dank!
        Musik FEG Langenthal
        TEXT;

    public function getDescription(): string
    {
        return 'Add survey invitation subject/body and participant last_emailed_at';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE survey ADD invitation_subject VARCHAR(255) NOT NULL DEFAULT '', ADD invitation_body LONGTEXT NOT NULL");
        $this->addSql(
            'UPDATE survey SET invitation_subject = :subject, invitation_body = :body',
            ['subject' => self::DEFAULT_SUBJECT, 'body' => self::DEFAULT_BODY],
        );
        $this->addSql('ALTER TABLE survey ALTER invitation_subject DROP DEFAULT');
        $this->addSql('ALTER TABLE survey_participant ADD last_emailed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE survey SET status = 'open' WHERE status = 'draft'");
        $this->addSql('ALTER TABLE survey DROP invitation_subject, DROP invitation_body');
        $this->addSql('ALTER TABLE survey_participant DROP last_emailed_at');
    }
}
