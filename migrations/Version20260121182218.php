<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260121182218 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    
public function up(Schema $schema): void
{
    // 1) Add columns as NULLable first (so existing rows don't break)
    $this->addSql('ALTER TABLE task ADD created_at DATETIME DEFAULT NULL, ADD updated_at DATETIME DEFAULT NULL');

    // 2) Backfill existing rows with sane values
    $this->addSql('UPDATE task SET created_at = NOW() WHERE created_at IS NULL OR created_at = "0000-00-00 00:00:00"');
    $this->addSql('UPDATE task SET updated_at = created_at WHERE updated_at IS NULL OR updated_at = "0000-00-00 00:00:00"');

    // 3) Now enforce NOT NULL on created_at
    $this->addSql('ALTER TABLE task MODIFY created_at DATETIME NOT NULL');
}


    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE task DROP created_at, DROP updated_at');
        $this->addSql('ALTER TABLE user DROP role');
    }
}
