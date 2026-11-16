<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add privacy-preserving daily API performance aggregates.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE performance_metric (
                id INT AUTO_INCREMENT NOT NULL,
                bucket_date DATE NOT NULL,
                route_name VARCHAR(120) NOT NULL,
                method VARCHAR(10) NOT NULL,
                status_code SMALLINT NOT NULL,
                request_count INT NOT NULL,
                error_count INT NOT NULL,
                total_duration_ms INT NOT NULL,
                max_duration_ms INT NOT NULL,
                INDEX performance_bucket_date (bucket_date),
                UNIQUE INDEX uniq_performance_bucket (bucket_date, route_name, method, status_code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE performance_metric');
    }
}
