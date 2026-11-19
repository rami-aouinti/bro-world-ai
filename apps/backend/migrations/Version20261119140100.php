<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119140100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist the exact approved brief version and approval timestamp.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE content_approvals (id UUID NOT NULL, content_idea_id UUID NOT NULL, version_hash VARCHAR(64) NOT NULL, approved_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
        $this->addSql('CREATE UNIQUE INDEX uniq_content_approval_idea ON content_approvals (content_idea_id)');
        $this->addSql('ALTER TABLE content_approvals ADD CONSTRAINT fk_content_approvals_idea FOREIGN KEY (content_idea_id) REFERENCES content_ideas (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_approvals DROP CONSTRAINT fk_content_approvals_idea');
        $this->addSql('DROP TABLE content_approvals');
    }
}
