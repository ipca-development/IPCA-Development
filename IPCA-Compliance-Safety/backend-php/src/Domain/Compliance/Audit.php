<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Domain\Compliance;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

final class Audit
{
    public function __construct(
        private string $id,               // UUID string
        private ?string $externalRef,
        private string $title,
        private string $authority,        // 'BCAA','FAA','INTERNAL','OTHER'
        private string $auditType,
        private string $status,           // 'PLANNED','IN_PROGRESS','PERFORMED','CLOSED'
        private ?DateTimeImmutable $startDate,
        private ?DateTimeImmutable $endDate,
        private ?DateTimeImmutable $closedDate,
        private ?string $subject,
        private string $createdBy,        // UUID of user
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt
    ) {}

    public static function create(
        string $title,
        string $authority,
        string $auditType,
        ?string $externalRef,
        ?string $subject,
        string $createdBy
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: Uuid::uuid4()->toString(),
            externalRef: $externalRef,
            title: $title,
            authority: $authority,
            auditType: $auditType,
            status: 'PLANNED',
            startDate: null,
            endDate: null,
            closedDate: null,
            subject: $subject,
            createdBy: $createdBy,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public function id(): string      { return $this->id; }
    public function title(): string   { return $this->title; }
    public function authority(): string { return $this->authority; }
    public function auditType(): string { return $this->auditType; }
    public function status(): string  { return $this->status; }
    public function createdBy(): string { return $this->createdBy; }
    public function createdAt(): DateTimeImmutable { return $this->createdAt; }
    public function updatedAt(): DateTimeImmutable { return $this->updatedAt; }
    public function externalRef(): ?string { return $this->externalRef; }
    public function subject(): ?string { return $this->subject; }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'external_ref' => $this->externalRef,
            'title'        => $this->title,
            'authority'    => $this->authority,
            'audit_type'   => $this->auditType,
            'status'       => $this->status,
            'subject'      => $this->subject,
            'created_by'   => $this->createdBy,
            'created_at'   => $this->createdAt->format(DATE_ATOM),
            'updated_at'   => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}