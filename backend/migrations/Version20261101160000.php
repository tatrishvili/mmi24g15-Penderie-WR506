<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store book authors, music artists and publishers independently of brand.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item ADD author VARCHAR(120) DEFAULT NULL, ADD artist VARCHAR(120) DEFAULT NULL, ADD publisher VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item DROP author, DROP artist, DROP publisher');
    }
}
