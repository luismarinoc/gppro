<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Doctrine\Behavior\CreatedAt;
use App\Doctrine\Behavior\CreatedTrait;
use App\Repository\MilestoneRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'kimai2_milestones')]
#[ORM\Index(columns: ['project_id'])]
#[ORM\Entity(repositoryClass: MilestoneRepository::class)]
class Milestone implements CreatedAt
{
    use CreatedTrait;

    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Project::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Project $project = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 150, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 150)]
    private ?string $name = null;

    #[ORM\Column(name: 'comment', type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(name: 'due_date', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dueDate = null;

    /**
     * @var Collection<int, Activity>
     */
    #[ORM\OneToMany(mappedBy: 'milestone', targetEntity: Activity::class)]
    private Collection $activities;

    public function __construct()
    {
        $this->activities = new ArrayCollection();
        $this->setCreatedAt(new \DateTimeImmutable('now', new \DateTimeZone('UTC')));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): Milestone
    {
        $this->project = $project;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): Milestone
    {
        $this->name = $name;

        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): Milestone
    {
        $this->comment = $comment;

        return $this;
    }

    public function getDueDate(): ?\DateTime
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTime $dueDate): Milestone
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    /**
     * @return Collection<int, Activity>
     */
    public function getActivities(): Collection
    {
        return $this->activities;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
