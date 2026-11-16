<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Allow item shares to be restricted to one accepted friend.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_share ADD recipient_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_ITEM_SHARE_RECIPIENT ON item_share (recipient_id)');
        $this->addSql('ALTER TABLE item_share ADD CONSTRAINT FK_ITEM_SHARE_RECIPIENT FOREIGN KEY (recipient_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item_share DROP FOREIGN KEY FK_ITEM_SHARE_RECIPIENT');
        $this->addSql('DROP INDEX IDX_ITEM_SHARE_RECIPIENT ON item_share');
        $this->addSql('ALTER TABLE item_share DROP recipient_id');
    }
}
