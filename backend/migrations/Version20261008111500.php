<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008111500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store the pending password hash and align generated unique index names.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE pending_registration '
            .'ADD password_hash VARCHAR(255) NOT NULL, '
            .'RENAME INDEX uniq_pending_registration_email TO UNIQ_FEFA0581E7927C74, '
            .'RENAME INDEX uniq_pending_registration_token TO UNIQ_FEFA0581B3BC57DA',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            'ALTER TABLE pending_registration '
            .'DROP password_hash, '
            .'RENAME INDEX UNIQ_FEFA0581E7927C74 TO uniq_pending_registration_email, '
            .'RENAME INDEX UNIQ_FEFA0581B3BC57DA TO uniq_pending_registration_token',
        );
    }
}
