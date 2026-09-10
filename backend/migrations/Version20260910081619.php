<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260910081619 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Рисунки заметок: один JSON-документ со штрихами на заметку';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE drawings (note_id UUID NOT NULL, lines JSON NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (note_id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE drawings');
    }
}
