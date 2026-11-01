<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261101160342 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Publish approved collection presentations to relationship-based and in-app audiences.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE collection_publication (id VARCHAR(24) NOT NULL, audience VARCHAR(20) NOT NULL, presentation JSON NOT NULL, updated_at DATETIME NOT NULL, workspace_id INT NOT NULL, INDEX IDX_3396FBF882D40A1F (workspace_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE collection_publication ADD CONSTRAINT FK_3396FBF882D40A1F FOREIGN KEY (workspace_id) REFERENCES social_workspace (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE collection_publication DROP FOREIGN KEY FK_3396FBF882D40A1F');
        $this->addSql('DROP TABLE collection_publication');
    }
}
