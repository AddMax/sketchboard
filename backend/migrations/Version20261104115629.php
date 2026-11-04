<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104115629 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Файлы: метаданные загруженных файлов, сами байты — в Garage';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE files (id UUID NOT NULL, name VARCHAR(255) NOT NULL, mime_type VARCHAR(127) NOT NULL, size INT NOT NULL, visibility VARCHAR(16) NOT NULL, owner VARCHAR(64) NOT NULL, uploaded_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX idx_files_owner ON files (owner)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE files');
    }
}
