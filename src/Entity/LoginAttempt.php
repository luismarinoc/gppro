<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\LoginAttemptRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Persisted audit trail row for every login attempt (success and failure).
 *
 * `user` is intentionally nullable: unknown-username attempts are still
 * logged (proposal locked decision #1/#5), with `attemptedUsername` always
 * capturing the raw submitted value.
 */
#[ORM\Table(name: 'gppro_login_attempts')]
#[ORM\Index(columns: ['created_at'], name: 'IDX_GPPRO_LOGIN_ATTEMPTS_CREATED_AT')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'IDX_GPPRO_LOGIN_ATTEMPTS_USER')]
#[ORM\Entity(repositoryClass: LoginAttemptRepository::class)]
class LoginAttempt
{
    public const OUTCOME_SUCCESS = 'success';
    public const OUTCOME_FAILURE = 'failure';

    /** @var string[] */
    public const OUTCOMES = [self::OUTCOME_SUCCESS, self::OUTCOME_FAILURE];

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'attempted_username', type: Types::STRING, length: 180, nullable: false)]
    #[Assert\NotBlank]
    private ?string $attemptedUsername = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'ip_address', type: Types::STRING, length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'user_agent', type: Types::STRING, length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'outcome', type: Types::STRING, length: 10, nullable: false)]
    #[Assert\Choice(choices: self::OUTCOMES)]
    private ?string $outcome = null;

    #[ORM\Column(name: 'failure_reason', type: Types::STRING, length: 120, nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE, nullable: false)]
    #[Assert\NotNull]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAttemptedUsername(): ?string
    {
        return $this->attemptedUsername;
    }

    public function setAttemptedUsername(string $attemptedUsername): self
    {
        $this->attemptedUsername = $attemptedUsername;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getOutcome(): ?string
    {
        return $this->outcome;
    }

    public function setOutcome(string $outcome): self
    {
        $this->outcome = $outcome;

        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): self
    {
        $this->failureReason = $failureReason;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
