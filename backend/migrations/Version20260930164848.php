<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260930164848 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, household_id INT NOT NULL, UNIQUE INDEX UNIQ_88BDF3E9E7927C74 (email), INDEX IDX_88BDF3E9E79FF843 (household_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE household (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, slug VARCHAR(180) NOT NULL, created_at DATETIME NOT NULL, UNIQUE INDEX UNIQ_54C32FC0989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE place (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, is_default TINYINT NOT NULL, created_at DATETIME NOT NULL, household_id INT NOT NULL, INDEX IDX_741D53CDE79FF843 (household_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE profile (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) NOT NULL, role VARCHAR(255) NOT NULL, pin_hash VARCHAR(255) DEFAULT NULL, avatar_color VARCHAR(7) DEFAULT NULL, created_at DATETIME NOT NULL, household_id INT NOT NULL, user_id INT DEFAULT NULL, INDEX IDX_8157AA0FE79FF843 (household_id), INDEX IDX_8157AA0FA76ED395 (user_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT FK_88BDF3E9E79FF843 FOREIGN KEY (household_id) REFERENCES household (id)');
        $this->addSql('ALTER TABLE place ADD CONSTRAINT FK_741D53CDE79FF843 FOREIGN KEY (household_id) REFERENCES household (id)');
        $this->addSql('ALTER TABLE profile ADD CONSTRAINT FK_8157AA0FE79FF843 FOREIGN KEY (household_id) REFERENCES household (id)');
        $this->addSql('ALTER TABLE profile ADD CONSTRAINT FK_8157AA0FA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE app_user DROP FOREIGN KEY FK_88BDF3E9E79FF843');
        $this->addSql('ALTER TABLE place DROP FOREIGN KEY FK_741D53CDE79FF843');
        $this->addSql('ALTER TABLE profile DROP FOREIGN KEY FK_8157AA0FE79FF843');
        $this->addSql('ALTER TABLE profile DROP FOREIGN KEY FK_8157AA0FA76ED395');
        $this->addSql('DROP TABLE app_user');
        $this->addSql('DROP TABLE household');
        $this->addSql('DROP TABLE place');
        $this->addSql('DROP TABLE profile');
    }
}
