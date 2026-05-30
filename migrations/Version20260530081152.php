<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260530081152 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE assignment (id INT AUTO_INCREMENT NOT NULL, staff_id INT NOT NULL, event_id INT NOT NULL, skill_id INT NOT NULL, INDEX IDX_30C544BAD4D57CD (staff_id), INDEX IDX_30C544BA71F7E88B (event_id), INDEX IDX_30C544BA5585C142 (skill_id), UNIQUE INDEX UNIQ_30C544BAD4D57CD71F7E88B5585C142 (staff_id, event_id, skill_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `event` (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(200) NOT NULL, date DATE NOT NULL, description LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE skill (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, skill_type_id INT NOT NULL, INDEX IDX_5E3DE477DFB912BA (skill_type_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE skill_type (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, color VARCHAR(7) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE staff (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(150) NOT NULL, is_leader TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE staff_skill (staff_id INT NOT NULL, skill_id INT NOT NULL, INDEX IDX_23D84CB0D4D57CD (staff_id), INDEX IDX_23D84CB05585C142 (skill_id), PRIMARY KEY (staff_id, skill_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE survey (id INT AUTO_INCREMENT NOT NULL, deadline DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE survey_event (survey_id INT NOT NULL, event_id INT NOT NULL, INDEX IDX_37DEF89FB3FE509D (survey_id), INDEX IDX_37DEF89F71F7E88B (event_id), PRIMARY KEY (survey_id, event_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE survey_participant (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(64) NOT NULL, remark LONGTEXT DEFAULT NULL, survey_id INT NOT NULL, staff_id INT NOT NULL, UNIQUE INDEX UNIQ_6F67B4145F37A13B (token), INDEX IDX_6F67B414B3FE509D (survey_id), INDEX IDX_6F67B414D4D57CD (staff_id), UNIQUE INDEX UNIQ_6F67B414B3FE509DD4D57CD (survey_id, staff_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE survey_response (id INT AUTO_INCREMENT NOT NULL, available TINYINT NOT NULL, participant_id INT NOT NULL, event_id INT NOT NULL, INDEX IDX_628C4DDC9D1C3019 (participant_id), INDEX IDX_628C4DDC71F7E88B (event_id), UNIQUE INDEX UNIQ_628C4DDC9D1C301971F7E88B (participant_id, event_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BAD4D57CD FOREIGN KEY (staff_id) REFERENCES staff (id)');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BA71F7E88B FOREIGN KEY (event_id) REFERENCES `event` (id)');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BA5585C142 FOREIGN KEY (skill_id) REFERENCES skill (id)');
        $this->addSql('ALTER TABLE skill ADD CONSTRAINT FK_5E3DE477DFB912BA FOREIGN KEY (skill_type_id) REFERENCES skill_type (id)');
        $this->addSql('ALTER TABLE staff_skill ADD CONSTRAINT FK_23D84CB0D4D57CD FOREIGN KEY (staff_id) REFERENCES staff (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE staff_skill ADD CONSTRAINT FK_23D84CB05585C142 FOREIGN KEY (skill_id) REFERENCES skill (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE survey_event ADD CONSTRAINT FK_37DEF89FB3FE509D FOREIGN KEY (survey_id) REFERENCES survey (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE survey_event ADD CONSTRAINT FK_37DEF89F71F7E88B FOREIGN KEY (event_id) REFERENCES `event` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE survey_participant ADD CONSTRAINT FK_6F67B414B3FE509D FOREIGN KEY (survey_id) REFERENCES survey (id)');
        $this->addSql('ALTER TABLE survey_participant ADD CONSTRAINT FK_6F67B414D4D57CD FOREIGN KEY (staff_id) REFERENCES staff (id)');
        $this->addSql('ALTER TABLE survey_response ADD CONSTRAINT FK_628C4DDC9D1C3019 FOREIGN KEY (participant_id) REFERENCES survey_participant (id)');
        $this->addSql('ALTER TABLE survey_response ADD CONSTRAINT FK_628C4DDC71F7E88B FOREIGN KEY (event_id) REFERENCES `event` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BAD4D57CD');
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BA71F7E88B');
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BA5585C142');
        $this->addSql('ALTER TABLE skill DROP FOREIGN KEY FK_5E3DE477DFB912BA');
        $this->addSql('ALTER TABLE staff_skill DROP FOREIGN KEY FK_23D84CB0D4D57CD');
        $this->addSql('ALTER TABLE staff_skill DROP FOREIGN KEY FK_23D84CB05585C142');
        $this->addSql('ALTER TABLE survey_event DROP FOREIGN KEY FK_37DEF89FB3FE509D');
        $this->addSql('ALTER TABLE survey_event DROP FOREIGN KEY FK_37DEF89F71F7E88B');
        $this->addSql('ALTER TABLE survey_participant DROP FOREIGN KEY FK_6F67B414B3FE509D');
        $this->addSql('ALTER TABLE survey_participant DROP FOREIGN KEY FK_6F67B414D4D57CD');
        $this->addSql('ALTER TABLE survey_response DROP FOREIGN KEY FK_628C4DDC9D1C3019');
        $this->addSql('ALTER TABLE survey_response DROP FOREIGN KEY FK_628C4DDC71F7E88B');
        $this->addSql('DROP TABLE assignment');
        $this->addSql('DROP TABLE `event`');
        $this->addSql('DROP TABLE skill');
        $this->addSql('DROP TABLE skill_type');
        $this->addSql('DROP TABLE staff');
        $this->addSql('DROP TABLE staff_skill');
        $this->addSql('DROP TABLE survey');
        $this->addSql('DROP TABLE survey_event');
        $this->addSql('DROP TABLE survey_participant');
        $this->addSql('DROP TABLE survey_response');
    }
}
