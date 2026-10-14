<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261008110500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add expiring, single-use pending email registrations.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            'CREATE TABLE pending_registration ('
            .'id INT AUTO_INCREMENT NOT NULL, '
            .'email VARCHAR(180) NOT NULL, '
            .'token_hash VARCHAR(64) NOT NULL, '
            .'expires_at DATETIME NOT NULL, '
            .'created_at DATETIME NOT NULL, '
            .'last_sent_at DATETIME NOT NULL, '
            .'UNIQUE INDEX uniq_pending_registration_email (email), '
            .'UNIQUE INDEX uniq_pending_registration_token (token_hash), '
            .'PRIMARY KEY (id)'
            .') DEFAULT CHARACTER SET utf8mb4',
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE pending_registration');
    }
}
