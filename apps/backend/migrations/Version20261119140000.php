<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add weekly planning, capacity allocation, content briefs and idempotent simulated handoffs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE content_ideas ALTER status TYPE VARCHAR(30)");
        $this->addSql("CREATE TABLE weekly_plans (id UUID NOT NULL, week_start DATE NOT NULL, capacity_person_a INT NOT NULL, capacity_person_b INT NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql("CREATE UNIQUE INDEX uniq_weekly_plan_start ON weekly_plans (week_start)");
        $this->addSql("CREATE TABLE plan_slots (id UUID NOT NULL, weekly_plan_id UUID NOT NULL, content_idea_id UUID NOT NULL, slot_type VARCHAR(20) NOT NULL, assignee VARCHAR(1) NOT NULL, estimated_minutes INT NOT NULL, PRIMARY KEY(id))");
        $this->addSql("CREATE UNIQUE INDEX uniq_plan_slot_idea ON plan_slots (content_idea_id)");
        $this->addSql("CREATE INDEX idx_plan_slots_plan ON plan_slots (weekly_plan_id)");
        $this->addSql("ALTER TABLE plan_slots ADD CONSTRAINT fk_plan_slots_plan FOREIGN KEY (weekly_plan_id) REFERENCES weekly_plans (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("ALTER TABLE plan_slots ADD CONSTRAINT fk_plan_slots_idea FOREIGN KEY (content_idea_id) REFERENCES content_ideas (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("CREATE TABLE content_briefs (id UUID NOT NULL, content_idea_id UUID NOT NULL, payload JSON NOT NULL, version_hash VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql("CREATE UNIQUE INDEX uniq_content_brief_idea ON content_briefs (content_idea_id)");
        $this->addSql("ALTER TABLE content_briefs ADD CONSTRAINT fk_content_briefs_idea FOREIGN KEY (content_idea_id) REFERENCES content_ideas (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE");
        $this->addSql("CREATE TABLE content_handoffs (id UUID NOT NULL, content_idea_id UUID NOT NULL, idempotency_key VARCHAR(128) NOT NULL, version_hash VARCHAR(64) NOT NULL, payload JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))");
        $this->addSql("CREATE UNIQUE INDEX uniq_handoff_idempotency_key ON content_handoffs (idempotency_key)");
        $this->addSql("CREATE INDEX idx_content_handoffs_idea ON content_handoffs (content_idea_id)");
        $this->addSql("ALTER TABLE content_handoffs ADD CONSTRAINT fk_content_handoffs_idea FOREIGN KEY (content_idea_id) REFERENCES content_ideas (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE content_handoffs DROP CONSTRAINT fk_content_handoffs_idea');
        $this->addSql('DROP TABLE content_handoffs');
        $this->addSql('ALTER TABLE content_briefs DROP CONSTRAINT fk_content_briefs_idea');
        $this->addSql('DROP TABLE content_briefs');
        $this->addSql('ALTER TABLE plan_slots DROP CONSTRAINT fk_plan_slots_plan');
        $this->addSql('ALTER TABLE plan_slots DROP CONSTRAINT fk_plan_slots_idea');
        $this->addSql('DROP TABLE plan_slots');
        $this->addSql('DROP TABLE weekly_plans');
    }
}
