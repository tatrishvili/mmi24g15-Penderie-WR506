<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261008080624 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE item (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, brand VARCHAR(120) DEFAULT NULL, color VARCHAR(60) DEFAULT NULL, size VARCHAR(30) DEFAULT NULL, material VARCHAR(120) DEFAULT NULL, `condition` VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, qr_token VARCHAR(32) NOT NULL, memory LONGTEXT DEFAULT NULL, notes LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, household_id INT NOT NULL, owner_profile_id INT NOT NULL, UNIQUE INDEX UNIQ_1F1B251E1AE26361 (qr_token), INDEX IDX_1F1B251EE79FF843 (household_id), INDEX IDX_1F1B251E9E908BFD (owner_profile_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE item_photo (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(255) NOT NULL, is_primary TINYINT NOT NULL, sort_order INT NOT NULL, item_id INT NOT NULL, INDEX IDX_3E109FC8126F525E (item_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT FK_1F1B251EE79FF843 FOREIGN KEY (household_id) REFERENCES household (id)');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT FK_1F1B251E9E908BFD FOREIGN KEY (owner_profile_id) REFERENCES profile (id)');
        $this->addSql('ALTER TABLE item_photo ADD CONSTRAINT FK_3E109FC8126F525E FOREIGN KEY (item_id) REFERENCES item (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251EE79FF843');
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251E9E908BFD');
        $this->addSql('ALTER TABLE item_photo DROP FOREIGN KEY FK_3E109FC8126F525E');
        $this->addSql('DROP TABLE item');
        $this->addSql('DROP TABLE item_photo');
    }
}
