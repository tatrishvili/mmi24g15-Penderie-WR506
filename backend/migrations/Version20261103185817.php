<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103185817 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add child credentials, explicit parents, approval inbox, family item audiences and repeatable room assignments without deleting existing data.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE child_account (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(30) NOT NULL, password_hash VARCHAR(255) NOT NULL, enabled TINYINT NOT NULL, session_version INT NOT NULL, must_change_password TINYINT NOT NULL, initial_password_expires_at DATETIME DEFAULT NULL, profile_id INT NOT NULL, UNIQUE INDEX UNIQ_1F821EBFF85E0677 (username), UNIQUE INDEX UNIQ_1F821EBFCCFA12B8 (profile_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE child_approval (id INT AUTO_INCREMENT NOT NULL, version INT DEFAULT 1 NOT NULL, status VARCHAR(15) NOT NULL, decided_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, kind VARCHAR(30) NOT NULL, proposal JSON NOT NULL, digest VARCHAR(64) NOT NULL, decided_by_id INT DEFAULT NULL, child_id INT NOT NULL, INDEX IDX_9CE7E88FE26B496B (decided_by_id), INDEX IDX_9CE7E88FDD62C21B (child_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE child_collection (id INT AUTO_INCREMENT NOT NULL, version INT DEFAULT 1 NOT NULL, item_ids JSON NOT NULL, name VARCHAR(100) NOT NULL, owner_id INT NOT NULL, INDEX IDX_18929B597E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE child_connection (id INT AUTO_INCREMENT NOT NULL, version INT DEFAULT 1 NOT NULL, state VARCHAR(12) NOT NULL, requester_approved TINYINT NOT NULL, recipient_approved TINYINT NOT NULL, recipient_accepted TINYINT NOT NULL, requester_approved_by_id INT DEFAULT NULL, recipient_approved_by_id INT DEFAULT NULL, requester_id INT NOT NULL, recipient_child_id INT DEFAULT NULL, recipient_relative_id INT DEFAULT NULL, INDEX IDX_CD288D0DFCA9397E (requester_approved_by_id), INDEX IDX_CD288D0D851069DD (recipient_approved_by_id), INDEX IDX_CD288D0DED442CF4 (requester_id), INDEX IDX_CD288D0D337A931F (recipient_child_id), INDEX IDX_CD288D0D3F62BFD8 (recipient_relative_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE child_publication (id INT AUTO_INCREMENT NOT NULL, revoked TINYINT NOT NULL, recipients JSON NOT NULL, snapshot JSON NOT NULL, approval_id INT NOT NULL, INDEX IDX_75B80057FE65F000 (approval_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE guardian_link (id INT AUTO_INCREMENT NOT NULL, state VARCHAR(12) NOT NULL, version INT DEFAULT 1 NOT NULL, relationship VARCHAR(10) NOT NULL, child_id INT NOT NULL, parent_id INT NOT NULL, invited_by_id INT NOT NULL, UNIQUE INDEX guardian_child_user (child_id, parent_id), INDEX IDX_DF442C89DD62C21B (child_id), INDEX IDX_DF442C89727ACA70 (parent_id), INDEX IDX_DF442C89A7B4A7E3 (invited_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE inbox_notification (id INT AUTO_INCREMENT NOT NULL, read_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, recipient VARCHAR(50) NOT NULL, kind VARCHAR(30) NOT NULL, reference_id INT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE child_account ADD CONSTRAINT FK_1F821EBFCCFA12B8 FOREIGN KEY (profile_id) REFERENCES profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE child_approval ADD CONSTRAINT FK_9CE7E88FE26B496B FOREIGN KEY (decided_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE child_approval ADD CONSTRAINT FK_9CE7E88FDD62C21B FOREIGN KEY (child_id) REFERENCES profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE child_collection ADD CONSTRAINT FK_18929B597E3C61F9 FOREIGN KEY (owner_id) REFERENCES profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE child_connection ADD CONSTRAINT FK_CD288D0DFCA9397E FOREIGN KEY (requester_approved_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE child_connection ADD CONSTRAINT FK_CD288D0D851069DD FOREIGN KEY (recipient_approved_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE child_connection ADD CONSTRAINT FK_CD288D0DED442CF4 FOREIGN KEY (requester_id) REFERENCES profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE child_connection ADD CONSTRAINT FK_CD288D0D337A931F FOREIGN KEY (recipient_child_id) REFERENCES profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE child_connection ADD CONSTRAINT FK_CD288D0D3F62BFD8 FOREIGN KEY (recipient_relative_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE child_publication ADD CONSTRAINT FK_75B80057FE65F000 FOREIGN KEY (approval_id) REFERENCES child_approval (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guardian_link ADD CONSTRAINT FK_DF442C89DD62C21B FOREIGN KEY (child_id) REFERENCES profile (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guardian_link ADD CONSTRAINT FK_DF442C89727ACA70 FOREIGN KEY (parent_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guardian_link ADD CONSTRAINT FK_DF442C89A7B4A7E3 FOREIGN KEY (invited_by_id) REFERENCES app_user (id)');
        $this->addSql('ALTER TABLE item ADD sharing_version INT DEFAULT 1 NOT NULL, ADD family_audience VARCHAR(15) DEFAULT \'legacy\' NOT NULL, ADD family_viewer_ids JSON DEFAULT NULL, ADD family_digest VARCHAR(64) DEFAULT NULL, ADD family_approved_by_id INT DEFAULT NULL');
        $this->addSql("UPDATE item SET family_viewer_ids = '[]'");
        $this->addSql("ALTER TABLE item MODIFY family_viewer_ids JSON NOT NULL, ALTER family_audience SET DEFAULT 'owner'");
        $this->addSql('ALTER TABLE item ADD CONSTRAINT FK_1F1B251E1F752A1B FOREIGN KEY (family_approved_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1F1B251E1F752A1B ON item (family_approved_by_id)');
        $this->addSql('ALTER TABLE profile ADD created_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE profile ADD CONSTRAINT FK_8157AA0FB03A8386 FOREIGN KEY (created_by_id) REFERENCES app_user (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_8157AA0FB03A8386 ON profile (created_by_id)');
        $this->addSql('ALTER TABLE room ADD kind VARCHAR(30) DEFAULT NULL, ADD common TINYINT DEFAULT 0 NOT NULL, ADD assigned_profile_ids JSON DEFAULT NULL');
        $this->addSql("UPDATE room SET assigned_profile_ids = '[]'");
        $this->addSql('ALTER TABLE room MODIFY assigned_profile_ids JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE child_account DROP FOREIGN KEY FK_1F821EBFCCFA12B8');
        $this->addSql('ALTER TABLE child_approval DROP FOREIGN KEY FK_9CE7E88FE26B496B');
        $this->addSql('ALTER TABLE child_approval DROP FOREIGN KEY FK_9CE7E88FDD62C21B');
        $this->addSql('ALTER TABLE child_collection DROP FOREIGN KEY FK_18929B597E3C61F9');
        $this->addSql('ALTER TABLE child_connection DROP FOREIGN KEY FK_CD288D0DFCA9397E');
        $this->addSql('ALTER TABLE child_connection DROP FOREIGN KEY FK_CD288D0D851069DD');
        $this->addSql('ALTER TABLE child_connection DROP FOREIGN KEY FK_CD288D0DED442CF4');
        $this->addSql('ALTER TABLE child_connection DROP FOREIGN KEY FK_CD288D0D337A931F');
        $this->addSql('ALTER TABLE child_connection DROP FOREIGN KEY FK_CD288D0D3F62BFD8');
        $this->addSql('ALTER TABLE child_publication DROP FOREIGN KEY FK_75B80057FE65F000');
        $this->addSql('ALTER TABLE guardian_link DROP FOREIGN KEY FK_DF442C89DD62C21B');
        $this->addSql('ALTER TABLE guardian_link DROP FOREIGN KEY FK_DF442C89727ACA70');
        $this->addSql('ALTER TABLE guardian_link DROP FOREIGN KEY FK_DF442C89A7B4A7E3');
        $this->addSql('DROP TABLE child_account');
        $this->addSql('DROP TABLE child_approval');
        $this->addSql('DROP TABLE child_collection');
        $this->addSql('DROP TABLE child_connection');
        $this->addSql('DROP TABLE child_publication');
        $this->addSql('DROP TABLE guardian_link');
        $this->addSql('DROP TABLE inbox_notification');
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251E1F752A1B');
        $this->addSql('DROP INDEX IDX_1F1B251E1F752A1B ON item');
        $this->addSql('ALTER TABLE item DROP sharing_version, DROP family_audience, DROP family_viewer_ids, DROP family_digest, DROP family_approved_by_id');
        $this->addSql('ALTER TABLE profile DROP FOREIGN KEY FK_8157AA0FB03A8386');
        $this->addSql('DROP INDEX IDX_8157AA0FB03A8386 ON profile');
        $this->addSql('ALTER TABLE profile DROP created_by_id');
        $this->addSql('ALTER TABLE room DROP kind, DROP common, DROP assigned_profile_ids');
    }
}
