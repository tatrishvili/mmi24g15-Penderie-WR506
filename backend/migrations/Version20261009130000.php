<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261009130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add rooms, nested storage locations, and optional item location/category data.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE room (id INT AUTO_INCREMENT NOT NULL, household_id INT NOT NULL, place_id INT NOT NULL, name VARCHAR(120) NOT NULL, INDEX IDX_729F519BE79FF843 (household_id), INDEX IDX_729F519BDA6A219 (place_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE storage_location (id INT AUTO_INCREMENT NOT NULL, household_id INT NOT NULL, room_id INT NOT NULL, parent_id INT DEFAULT NULL, name VARCHAR(120) NOT NULL, INDEX IDX_FD55B58CE79FF843 (household_id), INDEX IDX_FD55B58C54177093 (room_id), INDEX IDX_FD55B58C727ACA70 (parent_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE room ADD CONSTRAINT FK_729F5195E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE room ADD CONSTRAINT FK_729F51958D9F6D38 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE storage_location ADD CONSTRAINT FK_2D3BC2F5E79FF843 FOREIGN KEY (household_id) REFERENCES household (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE storage_location ADD CONSTRAINT FK_2D3BC2F554177093 FOREIGN KEY (room_id) REFERENCES room (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE storage_location ADD CONSTRAINT FK_2D3BC2F5727ACA70 FOREIGN KEY (parent_id) REFERENCES storage_location (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE item ADD type VARCHAR(50) DEFAULT NULL, ADD category VARCHAR(120) DEFAULT NULL, ADD room_id INT DEFAULT NULL, ADD storage_location_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_1F1B251E54177093 ON item (room_id)');
        $this->addSql('CREATE INDEX IDX_1F1B251ECDDD8AF ON item (storage_location_id)');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT FK_1F1B251E54177093 FOREIGN KEY (room_id) REFERENCES room (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE item ADD CONSTRAINT FK_1F1B251E94E8D1D0 FOREIGN KEY (storage_location_id) REFERENCES storage_location (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251E54177093');
        $this->addSql('ALTER TABLE item DROP FOREIGN KEY FK_1F1B251E94E8D1D0');
        $this->addSql('ALTER TABLE item DROP room_id, DROP storage_location_id, DROP type, DROP category');
        $this->addSql('DROP TABLE storage_location');
        $this->addSql('DROP TABLE room');
    }
}
