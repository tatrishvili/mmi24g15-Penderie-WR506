<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow a loan request to omit its requested return date.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_loan CHANGE due_at due_at DATE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE item_loan SET due_at = DATE(requested_at) WHERE due_at IS NULL');
        $this->addSql('ALTER TABLE item_loan CHANGE due_at due_at DATE NOT NULL');
    }
}
