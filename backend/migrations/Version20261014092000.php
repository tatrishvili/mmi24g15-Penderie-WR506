<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261014092000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align user_follow with the ORM mapping (canonical index names, drop stale DC2Type comment).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_follow CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user_follow RENAME INDEX IDX_7BF78C9E12C2E13B TO IDX_D665F4DAC24F853');
        $this->addSql('ALTER TABLE user_follow RENAME INDEX IDX_7BF78C9E36DEAE6F TO IDX_D665F4DD956F010');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE user_follow RENAME INDEX IDX_D665F4DAC24F853 TO IDX_7BF78C9E12C2E13B');
        $this->addSql('ALTER TABLE user_follow RENAME INDEX IDX_D665F4DD956F010 TO IDX_7BF78C9E36DEAE6F');
        $this->addSql('ALTER TABLE user_follow CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
