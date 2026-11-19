<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'content_approvals')]
#[ORM\UniqueConstraint(name: 'uniq_content_approval_idea', fields: ['contentIdea'])]
class ContentApproval
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', length: 36)]
    private string $id;

    #[ORM\OneToOne(targetEntity: ContentIdea::class)]
    #[ORM\JoinColumn(name: 'content_idea_id', nullable: false, onDelete: 'CASCADE')]
    private ContentIdea $contentIdea;

    #[ORM\Column(name: 'version_hash', length: 64)]
    private string $versionHash;

    #[ORM\Column(name: 'approved_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $approvedAt;

    public function __construct(ContentIdea $contentIdea, string $versionHash)
    {
        $this->id = Uuid::v7()->toRfc4122();
        $this->contentIdea = $contentIdea;
        $this->versionHash = $versionHash;
        $this->approvedAt = new \DateTimeImmutable();
    }

    public function getId(): string { return $this->id; }
    public function getContentIdea(): ContentIdea { return $this->contentIdea; }
    public function getVersionHash(): string { return $this->versionHash; }
    public function getApprovedAt(): \DateTimeImmutable { return $this->approvedAt; }
}
