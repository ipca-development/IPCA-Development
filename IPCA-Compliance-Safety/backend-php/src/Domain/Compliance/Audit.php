<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Domain\Compliance;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

final class Audit
{
    public function __construct(
        private string $id,
        private ?string $externalRef,
        private string $title,

        // NEW canonical fields
        private string $auditCategory,   // INTERNAL | CAA
        private string $auditEntity,     // BCAA / FAA / EPC / SPC / CAA name

        private string $auditType,
        private string $status,

        private ?DateTimeImmutable $startDate,
        private ?DateTimeImmutable $endDate,
        private ?DateTimeImmutable $closedDate,

        private ?string $subject,
        private string $createdBy,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt
    ) {}

    public static function create(
        string $title,
        string $auditCategory,
        string $auditEntity,
        string $auditType,
        ?string $externalRef,
        ?string $subject,
        string $createdBy,
        ?string $startDate = null, // YYYY-MM-DD
        ?string $endDate = null    // YYYY-MM-DD
    ): self {
        $now = new DateTimeImmutable();

        return new self(
            id: Uuid::uuid4()->toString(),
            externalRef: $externalRef,
            title: $title,
            auditCategory: $auditCategory ?: 'CAA',
            auditEntity: $auditEntity ?: 'UNKNOWN',
            auditType: $auditType,
            status: 'PLANNED',
            startDate: self::parseDate($startDate),
            endDate: self::parseDate($endDate),
            closedDate: null,
            subject: $subject,
            createdBy: $createdBy,
            createdAt: $now,
            updatedAt: $now
        );
    }

    private static function parseDate(?string $d): ?DateTimeImmutable
    {
        if (!$d) return null;
        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $d);
        return $dt ?: null;
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'external_ref'   => $this->externalRef,
            'title'          => $this->title,

            'audit_category' => $this->auditCategory,
            'audit_entity'   => $this->auditEntity,

            'audit_type'     => $this->auditType,
            'status'         => $this->status,

            'subject'        => $this->subject,
            'start_date'     => $this->startDate ? $this->startDate->format('Y-m-d') : null,
            'end_date'       => $this->endDate ? $this->endDate->format('Y-m-d') : null,
            'closed_date'    => $this->closedDate ? $this->closedDate->format('Y-m-d') : null,

            'created_by'     => $this->createdBy,
            'created_at'     => $this->createdAt->format(DATE_ATOM),
            'updated_at'     => $this->updatedAt->format(DATE_ATOM),
        ];
    }
}