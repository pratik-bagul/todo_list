<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260121182822 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add updated_at column; created_at already exists';
    }

    public function up(Schema $schema): void
    {
        // 1) Add updated_at as nullable (if not already present)
        // If you want to be extra defensive on MySQL 8+, you can change to:
        // $this->addSql("ALTER TABLE task ADD COLUMN IF NOT EXISTS updated_at DATETIME DEFAULT NULL");
        //$this->addSql('ALTER TABLE task ADD updated_at DATETIME DEFAULT NULL');

        // 2) Backfill updated_at using created_at when null
        $this->addSql('UPDATE task SET updated_at = created_at WHERE updated_at IS NULL');

        // 3) (Optional) make updated_at NOT NULL if you want strictness
        // $this->addSql('ALTER TABLE task MODIFY updated_at DATETIME NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Revert only what this migration added
        $this->addSql('ALTER TABLE task DROP updated_at');
    }
}
