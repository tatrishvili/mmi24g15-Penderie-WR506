<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102105511 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store explicitly approved account display names and sanitized community avatars.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE community_identity (id INT AUTO_INCREMENT NOT NULL, display_name VARCHAR(100) NOT NULL, photo LONGTEXT NOT NULL, owner_id INT NOT NULL, UNIQUE INDEX UNIQ_98D32CE37E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE community_identity ADD CONSTRAINT FK_98D32CE37E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE community_identity DROP FOREIGN KEY FK_98D32CE37E3C61F9');
        $this->addSql('DROP TABLE community_identity');
    }
}
