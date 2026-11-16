<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add account roles and last login timestamp.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD roles JSON DEFAULT NULL');
        $this->addSql('UPDATE app_user SET roles = JSON_ARRAY() WHERE roles IS NULL');
        $this->addSql('ALTER TABLE app_user MODIFY roles JSON NOT NULL');
        $this->addSql('ALTER TABLE app_user ADD last_login_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user DROP last_login_at');
        $this->addSql('ALTER TABLE app_user DROP roles');
    }
}
