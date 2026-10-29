<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261001093453 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE household_member (id INT AUTO_INCREMENT NOT NULL, created_at DATETIME NOT NULL, user_id INT NOT NULL, household_id INT NOT NULL, UNIQUE INDEX uniq_member (user_id, household_id), INDEX IDX_59EA3F6DA76ED395 (user_id), INDEX IDX_59EA3F6DE79FF843 (household_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE household_member ADD CONSTRAINT FK_59EA3F6DA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE household_member ADD CONSTRAINT FK_59EA3F6DE79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE app_user DROP FOREIGN KEY `FK_88BDF3E9E79FF843`');
        $this->addSql('DROP INDEX IDX_88BDF3E9E79FF843 ON app_user');
        $this->addSql('ALTER TABLE app_user ADD first_name VARCHAR(100) NOT NULL, DROP household_id');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE household_member DROP FOREIGN KEY FK_59EA3F6DA76ED395');
        $this->addSql('ALTER TABLE household_member DROP FOREIGN KEY FK_59EA3F6DE79FF843');
        $this->addSql('DROP TABLE household_member');
        $this->addSql('ALTER TABLE app_user ADD household_id INT NOT NULL, DROP first_name');
        $this->addSql('ALTER TABLE app_user ADD CONSTRAINT `FK_88BDF3E9E79FF843` FOREIGN KEY (household_id) REFERENCES household (id)');
        $this->addSql('CREATE INDEX IDX_88BDF3E9E79FF843 ON app_user (household_id)');
    }
}
