<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261103191334 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Expire additional-parent invitations and index each recipient notification inbox.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guardian_link ADD expires_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX inbox_recipient_id ON inbox_notification (recipient, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guardian_link DROP expires_at');
        $this->addSql('DROP INDEX inbox_recipient_id ON inbox_notification');
    }
}
