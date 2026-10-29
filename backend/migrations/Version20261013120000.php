<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add one-way account follows.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_follow (id INT AUTO_INCREMENT NOT NULL, follower_id INT NOT NULL, followed_id INT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_7BF78C9E12C2E13B (follower_id), INDEX IDX_7BF78C9E36DEAE6F (followed_id), UNIQUE INDEX uniq_user_follow_pair (follower_id, followed_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE user_follow ADD CONSTRAINT FK_7BF78C9E12C2E13B FOREIGN KEY (follower_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_follow ADD CONSTRAINT FK_7BF78C9E36DEAE6F FOREIGN KEY (followed_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_follow');
    }
}
