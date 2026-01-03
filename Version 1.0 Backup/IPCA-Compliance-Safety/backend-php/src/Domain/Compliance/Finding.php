<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Domain\Compliance;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

final class Finding
{
    public function __construct(
        private string $id,
        private string $auditId,
        private string $reference,
        private string $title,
        private string $classification,
        private string $status,
        private string $severity,
        private string $description,
        private ?string $regulationRef,
        private DateTimeImmutable $raisedDate,
        private ?DateTimeImmutable $targetDate,
        private ?DateTimeImmutable $closedDate,
        private ?int $domainId,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt
    ) {}

    public static function create(
        string $auditId,
        string $reference,
        string $title,
        string $classification,
        string $severity,
        string $description,
        ?string $regulationRef,
        ?int $domainId,
        ?DateTimeImmutable $targetDate
    ): self {
        $now = new DateTimeImmutable();
        return new self(
            id: Uuid::uuid4()->toString(),
            auditId: $auditId,
            reference: $reference,
            title: $title,
            classification: $classification,
            status: 'OPEN',
            severity: $severity,
            description: $description,
            regulationRef: $regulationRef,
            raisedDate: $now,
            targetDate: $targetDate,
            closedDate: null,
            domainId: $domainId,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public function id(): string { return $this->id; }
    public function auditId(): string { return $this->auditId; }

    // --- mutators for editing ---
    public function edit(
        string $reference,
        string $title,
        string $classification,
        string $severity,
        string $description,
        ?string $regulationRef,
        ?int $domainId,
        ?DateTimeImmutable $targetDate
    ): void {
        $this->reference      = $reference;
        $this->title          = $title;
        $this->classification = $classification;
        $this->severity       = $severity;
        $this->description    = $description;
        $this->regulationRef  = $regulationRef;
        $this->domainId       = $domainId;
        $this->targetDate     = $targetDate;
        $this->updatedAt      = new DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'audit_id'       => $this->auditId,
            'reference'      => $this->reference,
            'title'          => $this->title,
            'classification' => $this->classification,
            'status'         => $this->status,
            'severity'       => $this->severity,
            'description'    => $this->description,
            'regulation_ref' => $this->regulationRef,
            'raised_date'    => $this->raisedDate->format('Y-m-d'),
            'target_date'    => $this->targetDate?->format('Y-m-d'),
            'closed_date'    => $this->closedDate?->format('Y-m-d'),
            'domain_id'      => $this->domainId,
            'created_at'     => $this->createdAt->format(\DATE_ATOM),
            'updated_at'     => $this->updatedAt->format(\DATE_ATOM),
        ];
    }
}