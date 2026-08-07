<?php

/*
 * This file is part of the gppro time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Entity;

use App\Repository\QuotationEmailRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Table(name: 'gppro_quotation_emails')]
#[ORM\Index(columns: ['quotation_id'])]
#[ORM\UniqueConstraint(name: 'UNIQ_GPPRO_QUOTATION_EMAIL_TOKEN', columns: ['token_hash'])]
#[ORM\Entity(repositoryClass: QuotationEmailRepository::class)]
class QuotationEmail
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Quotation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Quotation $quotation;

    #[ORM\Column(name: 'recipient_email', type: Types::STRING, length: 255)]
    private string $recipientEmail;

    #[ORM\Column(name: 'token_hash', type: Types::STRING, length: 64)]
    private string $tokenHash;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'sent_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $sentAt;

    #[ORM\Column(name: 'response', type: Types::STRING, length: 8, nullable: true)]
    private ?string $response = null;

    #[ORM\Column(name: 'responded_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    #[ORM\Column(name: 'response_ip', type: Types::STRING, length: 45, nullable: true)]
    private ?string $responseIp = null;

    #[ORM\Column(name: 'response_user_agent', type: Types::STRING, length: 255, nullable: true)]
    private ?string $responseUserAgent = null;

    public function __construct(Quotation $quotation, string $recipientEmail, string $tokenHash, \DateTimeImmutable $expiresAt, \DateTimeImmutable $sentAt)
    {
        $this->quotation = $quotation;
        $this->recipientEmail = $recipientEmail;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->sentAt = $sentAt;
    }

    public function getId(): ?int { return $this->id; }

    public function getQuotation(): Quotation { return $this->quotation; }

    public function getRecipientEmail(): string { return $this->recipientEmail; }

    public function getTokenHash(): string { return $this->tokenHash; }

    public function getExpiresAt(): \DateTimeImmutable { return $this->expiresAt; }

    public function getSentAt(): \DateTimeImmutable { return $this->sentAt; }

    public function getResponse(): ?string { return $this->response; }

    public function getRespondedAt(): ?\DateTimeImmutable { return $this->respondedAt; }

    public function getAcceptedAt(): ?\DateTimeImmutable { return $this->response === 'accepted' ? $this->respondedAt : null; }

    public function getRejectedAt(): ?\DateTimeImmutable { return $this->response === 'rejected' ? $this->respondedAt : null; }

    public function getResponseIp(): ?string { return $this->responseIp; }

    public function getResponseUserAgent(): ?string { return $this->responseUserAgent; }

    public function recordResponse(string $response, \DateTimeImmutable $respondedAt, ?string $ip, ?string $userAgent): void
    {
        if ($this->response !== null) {
            throw new \DomainException('This quotation response link has already been used.');
        }
        if (!\in_array($response, ['accepted', 'rejected'], true)) {
            throw new \InvalidArgumentException('Unknown quotation response.');
        }

        $this->response = $response;
        $this->respondedAt = $respondedAt;
        $this->responseIp = $ip === null ? null : substr($ip, 0, 45);
        $this->responseUserAgent = $userAgent === null ? null : substr($userAgent, 0, 255);
    }
}
