<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * CR 1: events belong to exactly one survey; surveys get a title.
 *
 * Data handling:
 * - Each event is assigned to the survey with the lowest id it was linked to.
 * - Events linked to further surveys are copied into each of them; that survey's responses are moved to the copy
 *   and the event's assignments are duplicated onto the copy.
 * - Events not linked to any survey are deleted (incl. any responses/assignments).
 */
final class Version20261124084000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Events belong to a survey (replaces survey_event), add survey title, cascade deletes';
    }

    public function up(Schema $schema): void
    {
        // 1. Survey title, pre-filled for existing surveys
        $this->addSql("ALTER TABLE survey ADD title VARCHAR(200) NOT NULL DEFAULT ''");
        $this->addSql("UPDATE survey SET title = CONCAT('Umfrage ', DATE_FORMAT(deadline, '%d.%m.%Y'))");
        $this->addSql('ALTER TABLE survey ALTER title DROP DEFAULT');

        // 2. Event → survey relation (nullable while migrating data) and a temporary copy marker
        $this->addSql('ALTER TABLE `event` ADD survey_id INT DEFAULT NULL, ADD copied_from INT DEFAULT NULL');

        // 3. Assign every event to the lowest survey it is linked to
        $this->addSql('UPDATE `event` e SET e.survey_id = (
            SELECT MIN(se.survey_id) FROM survey_event se WHERE se.event_id = e.id
        )');

        // 4. Copy events that are shared with further surveys
        $this->addSql('INSERT INTO `event` (title, date, description, survey_id, copied_from)
            SELECT e.title, e.date, e.description, se.survey_id, e.id
            FROM survey_event se
            JOIN `event` e ON e.id = se.event_id
            WHERE se.survey_id <> e.survey_id');

        // 5. Move the responses of those surveys to their copy
        $this->addSql('UPDATE survey_response r
            JOIN survey_participant p ON p.id = r.participant_id
            JOIN `event` n ON n.copied_from = r.event_id AND n.survey_id = p.survey_id
            SET r.event_id = n.id');

        // 6. Duplicate assignments onto the copies
        $this->addSql('INSERT INTO assignment (staff_id, event_id, skill_id)
            SELECT a.staff_id, n.id, a.skill_id
            FROM assignment a
            JOIN `event` n ON n.copied_from = a.event_id');

        // 7. Delete events that are not part of any survey
        $this->addSql('DELETE FROM survey_response WHERE event_id IN (SELECT id FROM `event` WHERE survey_id IS NULL)');
        $this->addSql('DELETE FROM assignment WHERE event_id IN (SELECT id FROM `event` WHERE survey_id IS NULL)');
        $this->addSql('DELETE FROM `event` WHERE survey_id IS NULL');

        // 8. Finalise the event → survey relation, drop the join table
        $this->addSql('ALTER TABLE `event` DROP copied_from, MODIFY survey_id INT NOT NULL');
        $this->addSql('ALTER TABLE `event` ADD CONSTRAINT FK_3BAE0AA7B3FE509D FOREIGN KEY (survey_id) REFERENCES survey (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_3BAE0AA7B3FE509D ON `event` (survey_id)');
        $this->addSql('ALTER TABLE survey_event DROP FOREIGN KEY `FK_37DEF89F71F7E88B`');
        $this->addSql('ALTER TABLE survey_event DROP FOREIGN KEY `FK_37DEF89FB3FE509D`');
        $this->addSql('DROP TABLE survey_event');

        // 9. Cascade deletes so removing a survey or event never leaves dangling rows
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY `FK_30C544BA71F7E88B`');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BA71F7E88B FOREIGN KEY (event_id) REFERENCES `event` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE survey_participant DROP FOREIGN KEY `FK_6F67B414B3FE509D`');
        $this->addSql('ALTER TABLE survey_participant ADD CONSTRAINT FK_6F67B414B3FE509D FOREIGN KEY (survey_id) REFERENCES survey (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE survey_response DROP FOREIGN KEY `FK_628C4DDC71F7E88B`');
        $this->addSql('ALTER TABLE survey_response DROP FOREIGN KEY `FK_628C4DDC9D1C3019`');
        $this->addSql('ALTER TABLE survey_response ADD CONSTRAINT FK_628C4DDC71F7E88B FOREIGN KEY (event_id) REFERENCES `event` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE survey_response ADD CONSTRAINT FK_628C4DDC9D1C3019 FOREIGN KEY (participant_id) REFERENCES survey_participant (id) ON DELETE CASCADE');
    }

    /**
     * Restores the schema and the survey ↔ event links. Event copies created in up() and deleted orphan events
     * are not restored.
     */
    public function down(Schema $schema): void
    {
        $this->addSql('CREATE TABLE survey_event (survey_id INT NOT NULL, event_id INT NOT NULL, INDEX IDX_37DEF89FB3FE509D (survey_id), INDEX IDX_37DEF89F71F7E88B (event_id), PRIMARY KEY (survey_id, event_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('INSERT INTO survey_event (survey_id, event_id) SELECT survey_id, id FROM `event`');
        $this->addSql('ALTER TABLE survey_event ADD CONSTRAINT `FK_37DEF89F71F7E88B` FOREIGN KEY (event_id) REFERENCES `event` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE survey_event ADD CONSTRAINT `FK_37DEF89FB3FE509D` FOREIGN KEY (survey_id) REFERENCES survey (id) ON DELETE CASCADE');

        $this->addSql('ALTER TABLE `event` DROP FOREIGN KEY FK_3BAE0AA7B3FE509D');
        $this->addSql('DROP INDEX IDX_3BAE0AA7B3FE509D ON `event`');
        $this->addSql('ALTER TABLE `event` DROP survey_id');
        $this->addSql('ALTER TABLE survey DROP title');

        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BA71F7E88B');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT `FK_30C544BA71F7E88B` FOREIGN KEY (event_id) REFERENCES `event` (id)');
        $this->addSql('ALTER TABLE survey_participant DROP FOREIGN KEY FK_6F67B414B3FE509D');
        $this->addSql('ALTER TABLE survey_participant ADD CONSTRAINT `FK_6F67B414B3FE509D` FOREIGN KEY (survey_id) REFERENCES survey (id)');
        $this->addSql('ALTER TABLE survey_response DROP FOREIGN KEY FK_628C4DDC9D1C3019');
        $this->addSql('ALTER TABLE survey_response DROP FOREIGN KEY FK_628C4DDC71F7E88B');
        $this->addSql('ALTER TABLE survey_response ADD CONSTRAINT `FK_628C4DDC9D1C3019` FOREIGN KEY (participant_id) REFERENCES survey_participant (id)');
        $this->addSql('ALTER TABLE survey_response ADD CONSTRAINT `FK_628C4DDC71F7E88B` FOREIGN KEY (event_id) REFERENCES `event` (id)');
    }
}
