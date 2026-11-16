<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add account suspension history and reports for shared item links.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_share ADD moderation_disabled TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql(<<<'SQL'
            CREATE TABLE account_suspension (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                suspended_by_id INT NOT NULL,
                restored_by_id INT DEFAULT NULL,
                reason VARCHAR(40) NOT NULL,
                created_at DATETIME NOT NULL,
                restored_at DATETIME DEFAULT NULL,
                INDEX IDX_ACCOUNT_SUSPENSION_ACTIVE (user_id, restored_at),
                INDEX IDX_ACCOUNT_SUSPENSION_SUSPENDED_BY (suspended_by_id),
                INDEX IDX_ACCOUNT_SUSPENSION_RESTORED_BY (restored_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE account_suspension ADD CONSTRAINT FK_ACCOUNT_SUSPENSION_USER FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE account_suspension ADD CONSTRAINT FK_ACCOUNT_SUSPENSION_MODERATOR FOREIGN KEY (suspended_by_id) REFERENCES app_user (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE account_suspension ADD CONSTRAINT FK_ACCOUNT_SUSPENSION_RESTORED_BY FOREIGN KEY (restored_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql(<<<'SQL'
            CREATE TABLE shared_item_report (
                id INT AUTO_INCREMENT NOT NULL,
                item_share_id INT NOT NULL,
                reporter_id INT NOT NULL,
                reviewed_by_id INT DEFAULT NULL,
                reason VARCHAR(40) NOT NULL,
                details LONGTEXT DEFAULT NULL,
                status VARCHAR(20) NOT NULL,
                created_at DATETIME NOT NULL,
                reviewed_at DATETIME DEFAULT NULL,
                UNIQUE INDEX UNIQ_SHARED_REPORT_SHARE_REPORTER (item_share_id, reporter_id),
                INDEX IDX_SHARED_REPORT_STATUS_CREATED (status, created_at),
                INDEX IDX_SHARED_REPORT_REPORTER (reporter_id),
                INDEX IDX_SHARED_REPORT_REVIEWED_BY (reviewed_by_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql('ALTER TABLE shared_item_report ADD CONSTRAINT FK_SHARED_REPORT_SHARE FOREIGN KEY (item_share_id) REFERENCES item_share (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE shared_item_report ADD CONSTRAINT FK_SHARED_REPORT_REPORTER FOREIGN KEY (reporter_id) REFERENCES app_user (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE shared_item_report ADD CONSTRAINT FK_SHARED_REPORT_MODERATOR FOREIGN KEY (reviewed_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE shared_item_report DROP FOREIGN KEY FK_SHARED_REPORT_SHARE');
        $this->addSql('ALTER TABLE shared_item_report DROP FOREIGN KEY FK_SHARED_REPORT_REPORTER');
        $this->addSql('ALTER TABLE shared_item_report DROP FOREIGN KEY FK_SHARED_REPORT_MODERATOR');
        $this->addSql('DROP TABLE shared_item_report');
        $this->addSql('ALTER TABLE account_suspension DROP FOREIGN KEY FK_ACCOUNT_SUSPENSION_USER');
        $this->addSql('ALTER TABLE account_suspension DROP FOREIGN KEY FK_ACCOUNT_SUSPENSION_MODERATOR');
        $this->addSql('ALTER TABLE account_suspension DROP FOREIGN KEY FK_ACCOUNT_SUSPENSION_RESTORED_BY');
        $this->addSql('DROP TABLE account_suspension');
        $this->addSql('ALTER TABLE item_share DROP moderation_disabled');
    }
}
