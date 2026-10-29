<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261029215520 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add private social workspaces, expiring item shares, visibility and loan history.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE item_loan (id INT AUTO_INCREMENT NOT NULL, item_name VARCHAR(255) NOT NULL, status VARCHAR(20) NOT NULL, message LONGTEXT NOT NULL, due_at DATE NOT NULL, requested_at DATETIME NOT NULL, accepted_at DATETIME DEFAULT NULL, handed_at DATETIME DEFAULT NULL, returned_at DATETIME DEFAULT NULL, item_id INT DEFAULT NULL, owner_id INT NOT NULL, borrower_id INT NOT NULL, INDEX IDX_DE349F8126F525E (item_id), INDEX IDX_DE349F87E3C61F9 (owner_id), INDEX IDX_DE349F811CE312B (borrower_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE item_share (id INT AUTO_INCREMENT NOT NULL, token_hash VARCHAR(64) NOT NULL, revoked TINYINT NOT NULL, verified_only TINYINT NOT NULL, expires_at DATETIME NOT NULL, created_at DATETIME NOT NULL, owner_id INT NOT NULL, item_id INT NOT NULL, UNIQUE INDEX UNIQ_C5A1868AB3BC57DA (token_hash), INDEX IDX_C5A1868A7E3C61F9 (owner_id), INDEX IDX_C5A1868A126F525E (item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE social_workspace (id INT AUTO_INCREMENT NOT NULL, profile JSON NOT NULL, collections JSON NOT NULL, pins JSON NOT NULL, version INT DEFAULT 1 NOT NULL, owner_id INT NOT NULL, household_id INT NOT NULL, UNIQUE INDEX UNIQ_WORKSPACE_OWNER_HOUSEHOLD (owner_id, household_id), INDEX IDX_BFC25D6E7E3C61F9 (owner_id), INDEX IDX_BFC25D6EE79FF843 (household_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE item_loan ADD CONSTRAINT FK_DE349F8126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE item_loan ADD CONSTRAINT FK_DE349F87E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE item_loan ADD CONSTRAINT FK_DE349F811CE312B FOREIGN KEY (borrower_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE item_share ADD CONSTRAINT FK_C5A1868A7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item_share ADD CONSTRAINT FK_C5A1868A126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE social_workspace ADD CONSTRAINT FK_BFC25D6E7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE social_workspace ADD CONSTRAINT FK_BFC25D6EE79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item ADD visibility VARCHAR(20) DEFAULT \'private\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item_loan DROP FOREIGN KEY FK_DE349F8126F525E');
        $this->addSql('ALTER TABLE item_loan DROP FOREIGN KEY FK_DE349F87E3C61F9');
        $this->addSql('ALTER TABLE item_loan DROP FOREIGN KEY FK_DE349F811CE312B');
        $this->addSql('ALTER TABLE item_share DROP FOREIGN KEY FK_C5A1868A7E3C61F9');
        $this->addSql('ALTER TABLE item_share DROP FOREIGN KEY FK_C5A1868A126F525E');
        $this->addSql('ALTER TABLE social_workspace DROP FOREIGN KEY FK_BFC25D6E7E3C61F9');
        $this->addSql('ALTER TABLE social_workspace DROP FOREIGN KEY FK_BFC25D6EE79FF843');
        $this->addSql('DROP TABLE item_loan');
        $this->addSql('DROP TABLE item_share');
        $this->addSql('DROP TABLE social_workspace');
        $this->addSql('ALTER TABLE item DROP visibility');
    }
}
