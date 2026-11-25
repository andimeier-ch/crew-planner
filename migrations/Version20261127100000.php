<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CR 8: assignment e-mails. Surveys get their own assignment subject and body (existing surveys get the default
 * text), and participants remember when they last received the assignment e-mail.
 */
final class Version20261127100000 extends AbstractMigration
{
    private const DEFAULT_SUBJECT = 'Einteilung: {surveyTitle}';

    private const DEFAULT_BODY = <<<'TEXT'
        Hallo {firstName}

        Die Einteilung für «{surveyTitle}» ist fertig. Hier siehst du, wann du eingeteilt bist, und den gesamten Plan:

        {plan}

        Vielen Dank für deinen Einsatz!
        Musik FEG Langenthal
        TEXT;

    public function getDescription(): string
    {
        return 'Add survey assignment subject/body and participant last assignment sent date';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE survey ADD assignment_subject VARCHAR(255) NOT NULL DEFAULT '', ADD assignment_body LONGTEXT NOT NULL");
        $this->addSql(
            'UPDATE survey SET assignment_subject = :subject, assignment_body = :body',
            ['subject' => self::DEFAULT_SUBJECT, 'body' => self::DEFAULT_BODY],
        );
        $this->addSql('ALTER TABLE survey ALTER assignment_subject DROP DEFAULT');
        $this->addSql('ALTER TABLE survey_participant ADD last_assignment_sent_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE survey DROP assignment_subject, DROP assignment_body');
        $this->addSql('ALTER TABLE survey_participant DROP last_assignment_sent_at');
    }
}
