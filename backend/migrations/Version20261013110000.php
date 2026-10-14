<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align friend-request indexes and immutable timestamp mapping.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE friend_request RENAME INDEX IDX_F4D5C1F4F624B39D TO IDX_F284D94F624B39D');
        $this->addSql('ALTER TABLE friend_request RENAME INDEX IDX_F4D5C1F4A4F4A4D TO IDX_F284D94E92F8F78');
        $this->addSql('ALTER TABLE friend_request CHANGE created_at created_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE friend_request RENAME INDEX IDX_F284D94F624B39D TO IDX_F4D5C1F4F624B39D');
        $this->addSql('ALTER TABLE friend_request RENAME INDEX IDX_F284D94E92F8F78 TO IDX_F4D5C1F4A4F4A4D');
        $this->addSql('ALTER TABLE friend_request CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
