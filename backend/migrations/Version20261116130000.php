<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store per-account dismissals of personal activity entries.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE activity_dismissal (
                id INT AUTO_INCREMENT NOT NULL,
                activity_log_id INT NOT NULL,
                actor_key VARCHAR(50) NOT NULL,
                UNIQUE INDEX uniq_activity_dismissal_actor_log (actor_key, activity_log_id),
                INDEX IDX_ACTIVITY_DISMISSAL_LOG (activity_log_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE activity_dismissal ADD CONSTRAINT FK_ACTIVITY_DISMISSAL_LOG FOREIGN KEY (activity_log_id) REFERENCES activity_log (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE activity_dismissal DROP FOREIGN KEY FK_ACTIVITY_DISMISSAL_LOG');
        $this->addSql('DROP TABLE activity_dismissal');
    }
}
