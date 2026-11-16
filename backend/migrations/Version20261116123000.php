<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill inbox notifications for pending loan requests.';
    }

    public function up(Schema $schema): void
    {
        $loans = $this->connection->fetchAllAssociative("SELECT id, owner_id FROM item_loan WHERE status = 'pending'");
        foreach ($loans as $loan) {
            $recipient = 'adult:'.(int) $loan['owner_id'];
            $loanId = (int) $loan['id'];
            $existing = $this->connection->fetchOne(
                'SELECT id FROM inbox_notification WHERE recipient = ? AND kind = ? AND reference_id = ?',
                [$recipient, 'loan_request', $loanId],
            );
            if (false === $existing) {
                $this->addSql(
                    'INSERT INTO inbox_notification (recipient, kind, reference_id, read_at, created_at) VALUES (?, ?, ?, NULL, CURRENT_TIMESTAMP)',
                    [$recipient, 'loan_request', $loanId],
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Backfilled loan request notifications are user-visible and must be preserved.');
    }
}
