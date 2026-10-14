<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261013100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add adult-to-adult friend requests and accepted friendships.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE app_user ADD email_verified TINYINT(1) DEFAULT 1 NOT NULL');
        $this->addSql("UPDATE app_user u INNER JOIN profile p ON p.user_id = u.id SET u.email_verified = 0 WHERE p.role = 'partner'");
        $this->addSql('CREATE TABLE friend_request (id INT AUTO_INCREMENT NOT NULL, sender_id INT NOT NULL, recipient_id INT NOT NULL, pair_key VARCHAR(43) NOT NULL, status VARCHAR(10) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_F4D5C1F4F624B39D (sender_id), INDEX IDX_F4D5C1F4A4F4A4D (recipient_id), UNIQUE INDEX uniq_friend_request_pair (pair_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F4D5C1F4F624B39D FOREIGN KEY (sender_id) REFERENCES app_user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE friend_request ADD CONSTRAINT FK_F4D5C1F4A4F4A4D FOREIGN KEY (recipient_id) REFERENCES app_user (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE friend_request');
        $this->addSql('ALTER TABLE app_user DROP email_verified');
    }
}
