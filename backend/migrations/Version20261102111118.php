<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102111118 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add separately approved public bios, keeping all existing private bios unshared.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE community_identity ADD bio LONGTEXT DEFAULT \'\' NOT NULL, ADD bio_shared TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE community_identity DROP bio, DROP bio_shared');
    }
}
