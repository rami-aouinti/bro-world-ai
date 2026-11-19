<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the first content idea aggregate table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE content_ideas (
                id UUID NOT NULL,
                title VARCHAR(160) NOT NULL,
                topic VARCHAR(255) NOT NULL,
                target_audience VARCHAR(255) NOT NULL,
                tone VARCHAR(80) NOT NULL,
                channels JSON NOT NULL,
                status VARCHAR(30) NOT NULL,
                created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                PRIMARY KEY(id)
            )
        SQL);
        $this->addSql('CREATE INDEX idx_content_ideas_status ON content_ideas (status)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE content_ideas');
    }
}
