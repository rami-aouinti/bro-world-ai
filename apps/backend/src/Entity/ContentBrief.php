<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'content_briefs')]
#[ORM\UniqueConstraint(name: 'uniq_content_brief_idea', fields: ['contentIdea'])]
class ContentBrief
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', length: 36)]
    private string $id;

    #[ORM\OneToOne(targetEntity: ContentIdea::class)]
    #[ORM\JoinColumn(name: 'content_idea_id', nullable: false, onDelete: 'CASCADE')]
    private ContentIdea $contentIdea;

    #[ORM\Column(type: 'json')]
    private array $payload;

    #[ORM\Column(name: 'version_hash', length: 64)]
    private string $versionHash;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(ContentIdea $contentIdea, array $payload, string $versionHash)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->contentIdea = $contentIdea;
        $this->payload = $payload;
        $this->versionHash = $versionHash;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function replacePayload(array $payload, string $versionHash): void
    {
        $this->payload = $payload;
        $this->versionHash = $versionHash;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getContentIdea(): ContentIdea { return $this->contentIdea; }
    public function getPayload(): array { return $this->payload; }
    public function getVersionHash(): string { return $this->versionHash; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
