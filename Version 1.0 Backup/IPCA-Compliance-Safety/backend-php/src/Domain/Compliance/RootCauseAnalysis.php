<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Domain\Compliance;

use DateTimeImmutable;

final class RootCauseAnalysis
{
    public function __construct(
        private int $id,
        private string $findingId,
        private string $why1,
        private ?string $why2,
        private ?string $why3,
        private ?string $why4,
        private ?string $why5,
        private ?string $rootCause,
        private ?string $preventiveTheme,
        private string $createdBy,
        private DateTimeImmutable $createdAt
    ) {}

    public static function create(
        string $findingId,
        string $why1,
        ?string $why2,
        ?string $why3,
        ?string $why4,
        ?string $why5,
        ?string $rootCause,
        ?string $preventiveTheme,
        string $createdBy
    ): self {
        return new self(
            id: 0,
            findingId: $findingId,
            why1: $why1,
            why2: $why2,
            why3: $why3,
            why4: $why4,
            why5: $why5,
            rootCause: $rootCause,
            preventiveTheme: $preventiveTheme,
            createdBy: $createdBy,
            createdAt: new DateTimeImmutable()
        );
    }

    public function findingId(): string { return $this->findingId; }

    public function toArray(): array
    {
        return [
            'finding_id'       => $this->findingId,
            'why1'             => $this->why1,
            'why2'             => $this->why2,
            'why3'             => $this->why3,
            'why4'             => $this->why4,
            'why5'             => $this->why5,
            'root_cause'       => $this->rootCause,
            'preventive_theme' => $this->preventiveTheme,
            'created_by'       => $this->createdBy,
            'created_at'       => $this->createdAt->format(DATE_ATOM),
        ];
    }
}