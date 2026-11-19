<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Enum\ContentIdeaStatus;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'content_ideas')]
#[ORM\Index(name: 'idx_content_ideas_status', fields: ['status'])]
#[ORM\HasLifecycleCallbacks]
#[ApiResource(
    operations: [
        new GetCollection(),
        new Get(),
        new Post(),
    ],
    normalizationContext: ['groups' => ['content_idea:read']],
    denormalizationContext: ['groups' => ['content_idea:write']],
)]
class ContentIdea
{
    #[ORM\Id]
    #[ORM\Column(type: 'guid', length: 36, unique: true)]
    #[Groups(['content_idea:read'])]
    private string $id;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 160)]
    #[Groups(['content_idea:read', 'content_idea:write'])]
    private string $title = '';

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['content_idea:read', 'content_idea:write'])]
    private string $topic = '';

    #[ORM\Column(name: 'target_audience', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    #[Groups(['content_idea:read', 'content_idea:write'])]
    private string $targetAudience = '';

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 80)]
    #[Groups(['content_idea:read', 'content_idea:write'])]
    private string $tone = '';

    /** @var list<string> */
    #[ORM\Column(type: 'json')]
    #[Assert\Count(min: 1, minMessage: 'Select at least one channel.')]
    #[Assert\All([
        new Assert\Choice(choices: ['instagram', 'facebook', 'tiktok', 'linkedin']),
    ])]
    #[Groups(['content_idea:read', 'content_idea:write'])]
    private array $channels = [];

    #[ORM\Column(length: 30, enumType: ContentIdeaStatus::class)]
    #[Groups(['content_idea:read'])]
    private ContentIdeaStatus $status = ContentIdeaStatus::Draft;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    #[Groups(['content_idea:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    #[Groups(['content_idea:read'])]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $now = new \DateTimeImmutable();
        $this->id = Uuid::v7()->toRfc4122();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = trim($title);

        return $this;
    }

    public function getTopic(): string
    {
        return $this->topic;
    }

    public function setTopic(string $topic): self
    {
        $this->topic = trim($topic);

        return $this;
    }

    public function getTargetAudience(): string
    {
        return $this->targetAudience;
    }

    public function setTargetAudience(string $targetAudience): self
    {
        $this->targetAudience = trim($targetAudience);

        return $this;
    }

    public function getTone(): string
    {
        return $this->tone;
    }

    public function setTone(string $tone): self
    {
        $this->tone = trim($tone);

        return $this;
    }

    /** @return list<string> */
    public function getChannels(): array
    {
        return $this->channels;
    }

    /** @param list<string> $channels */
    public function setChannels(array $channels): self
    {
        $this->channels = array_values(array_unique($channels));

        return $this;
    }

    public function getStatus(): ContentIdeaStatus
    {
        return $this->status;
    }

    public function setStatus(ContentIdeaStatus $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    #[ORM\PreUpdate]
    public function refreshUpdatedAt(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
