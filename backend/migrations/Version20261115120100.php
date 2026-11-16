<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115120100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add append-only activity log and anonymous page visits.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE activity_log (
                id INT AUTO_INCREMENT NOT NULL,
                household_id INT DEFAULT NULL,
                actor_user_id INT DEFAULT NULL,
                actor_profile_id INT DEFAULT NULL,
                action VARCHAR(80) NOT NULL,
                subject_type VARCHAR(60) DEFAULT NULL,
                subject_id INT DEFAULT NULL,
                metadata JSON NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_FD06F647FED607AB (actor_profile_id),
                INDEX activity_household_created (household_id, created_at),
                INDEX activity_actor_created (actor_user_id, created_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_ACTIVITY_HOUSEHOLD FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_ACTIVITY_ACTOR_USER FOREIGN KEY (actor_user_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE activity_log ADD CONSTRAINT FK_ACTIVITY_ACTOR_PROFILE FOREIGN KEY (actor_profile_id) REFERENCES profile (id) ON DELETE SET NULL');

        $this->addSql(<<<'SQL'
            CREATE TABLE page_visit (
                id INT AUTO_INCREMENT NOT NULL,
                household_id INT DEFAULT NULL,
                visitor_id VARCHAR(64) DEFAULT NULL,
                page VARCHAR(40) NOT NULL,
                locale VARCHAR(5) DEFAULT NULL,
                authenticated TINYINT(1) NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX visit_created (created_at),
                INDEX visit_household_created (household_id, created_at),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE page_visit ADD CONSTRAINT FK_VISIT_HOUSEHOLD FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_ACTIVITY_HOUSEHOLD');
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_ACTIVITY_ACTOR_USER');
        $this->addSql('ALTER TABLE activity_log DROP FOREIGN KEY FK_ACTIVITY_ACTOR_PROFILE');
        $this->addSql('DROP TABLE activity_log');
        $this->addSql('ALTER TABLE page_visit DROP FOREIGN KEY FK_VISIT_HOUSEHOLD');
        $this->addSql('DROP TABLE page_visit');
    }
}
