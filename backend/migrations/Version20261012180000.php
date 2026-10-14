<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261012180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store adult registration usernames and self-declared birth dates.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD username VARCHAR(30) DEFAULT NULL, ADD birth_date DATE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_APP_USER_USERNAME ON app_user (username)');
        $this->addSql('ALTER TABLE pending_registration ADD username VARCHAR(30) DEFAULT NULL, ADD birth_date DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pending_registration DROP username, DROP birth_date');
        $this->addSql('DROP INDEX UNIQ_APP_USER_USERNAME ON app_user');
        $this->addSql('ALTER TABLE app_user DROP username, DROP birth_date');
    }
}
