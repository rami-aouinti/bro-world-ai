<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'content_handoffs', indexes: [new ORM\Index(name: 'idx_content_handoffs_idea', columns: ['content_idea_id'])])]
#[ORM\UniqueConstraint(name: 'uniq_handoff_idempotency_key', columns: ['idempotency_key'])]
class ContentHandoff
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: ContentIdea::class)]
    #[ORM\JoinColumn(name: 'content_idea_id', nullable: false, onDelete: 'CASCADE')]
    private ContentIdea $contentIdea;

    #[ORM\Column(name: 'idempotency_key', length: 128)]
    private string $idempotencyKey;

    #[ORM\Column(name: 'version_hash', length: 64)]
    private string $versionHash;

    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(ContentIdea $contentIdea, string $idempotencyKey, string $versionHash, array $payload)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->contentIdea = $contentIdea;
        $this->idempotencyKey = $idempotencyKey;
        $this->versionHash = $versionHash;
        $this->payload = $payload;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getContentIdea(): ContentIdea { return $this->contentIdea; }
    public function getIdempotencyKey(): string { return $this->idempotencyKey; }
    public function getVersionHash(): string { return $this->versionHash; }
    public function getPayload(): array { return $this->payload; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
