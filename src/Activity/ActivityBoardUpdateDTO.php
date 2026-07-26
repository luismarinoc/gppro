<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Activity;

use App\Entity\ActivityBoardPriority;
use App\Entity\ActivityBoardStatus;
use App\Validator\ValidationException;

/**
 * Parses the PATCH request body for the activity board card update endpoint.
 *
 * Every key is optional (partial-update semantics): a missing key means "do
 * not touch this field", while an explicit `null` for a nullable field means
 * "clear this field". Presence and value are tracked separately per field via
 * the has*()/get*() pairs below - the caller MUST check has*() before
 * relying on get*(), since get*() returning null is ambiguous between
 * "not provided" and "explicitly cleared".
 */
final class ActivityBoardUpdateDTO
{
    private bool $hasStatus = false;
    private ?ActivityBoardStatus $status = null;
    private bool $hasPriority = false;
    private ?ActivityBoardPriority $priority = null;
    private bool $hasDueDate = false;
    private ?\DateTime $dueDate = null;
    private bool $hasAssignedTo = false;
    private ?int $assignedToId = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();

        if (\array_key_exists('status', $data)) {
            $dto->hasStatus = true;
            $dto->status = self::parseStatus($data['status']);
        }

        if (\array_key_exists('priority', $data)) {
            $dto->hasPriority = true;
            $dto->priority = self::parsePriority($data['priority']);
        }

        if (\array_key_exists('dueDate', $data)) {
            $dto->hasDueDate = true;
            $dto->dueDate = self::parseDueDate($data['dueDate']);
        }

        if (\array_key_exists('assignedTo', $data)) {
            $dto->hasAssignedTo = true;
            $dto->assignedToId = self::parseAssignedTo($data['assignedTo']);
        }

        return $dto;
    }

    private static function parseStatus(mixed $value): ActivityBoardStatus
    {
        if (!\is_string($value) || '' === $value) {
            throw new ValidationException('Status must be a non-empty string');
        }

        $status = ActivityBoardStatus::tryFrom($value);
        if (null === $status) {
            throw new ValidationException(\sprintf('Unknown activity board status "%s"', $value));
        }

        return $status;
    }

    private static function parsePriority(mixed $value): ?ActivityBoardPriority
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value) || '' === $value) {
            throw new ValidationException('Priority must be a non-empty string or null');
        }

        $priority = ActivityBoardPriority::tryFrom($value);
        if (null === $priority) {
            throw new ValidationException(\sprintf('Unknown activity board priority "%s"', $value));
        }

        return $priority;
    }

    private static function parseDueDate(mixed $value): ?\DateTime
    {
        if (null === $value) {
            return null;
        }

        if (!\is_string($value) || '' === $value) {
            throw new ValidationException('Due date must be a non-empty date string or null');
        }

        try {
            return new \DateTime($value);
        } catch (\Exception) {
            throw new ValidationException(\sprintf('Invalid due date "%s"', $value));
        }
    }

    private static function parseAssignedTo(mixed $value): ?int
    {
        if (null === $value) {
            return null;
        }

        if (!\is_int($value)) {
            throw new ValidationException('Assigned user id must be an integer or null');
        }

        return $value;
    }

    public function hasStatus(): bool
    {
        return $this->hasStatus;
    }

    public function getStatus(): ?ActivityBoardStatus
    {
        return $this->status;
    }

    public function hasPriority(): bool
    {
        return $this->hasPriority;
    }

    public function getPriority(): ?ActivityBoardPriority
    {
        return $this->priority;
    }

    public function hasDueDate(): bool
    {
        return $this->hasDueDate;
    }

    public function getDueDate(): ?\DateTime
    {
        return $this->dueDate;
    }

    public function hasAssignedTo(): bool
    {
        return $this->hasAssignedTo;
    }

    public function getAssignedToId(): ?int
    {
        return $this->assignedToId;
    }
}
