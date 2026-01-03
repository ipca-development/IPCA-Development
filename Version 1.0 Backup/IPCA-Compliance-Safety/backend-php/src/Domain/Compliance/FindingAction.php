<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Domain\Compliance;

use DateTimeImmutable;

final class FindingAction
{
    public function __construct(
        private int $id,
        private string $findingId,
        private string $actionType,
        private string $description,
        private ?string $responsibleId,
        private ?DateTimeImmutable $dueDate,
        private ?DateTimeImmutable $completedAt,
        private string $effectiveness,
        private DateTimeImmutable $createdAt
    ) {}

    public static function create(
        string $findingId,
        string $actionType,
        string $description,
        ?string $responsibleId,
        ?DateTimeImmutable $dueDate
    ): self {
        return new self(
            id: 0,
            findingId: $findingId,
            actionType: $actionType,
            description: $description,
            responsibleId: $responsibleId,
            dueDate: $dueDate,
            completedAt: null,
            effectiveness: 'NOT_EVALUATED',
            createdAt: new DateTimeImmutable()
        );
    }

    public function id(): int { return $this->id; }
    public function findingId(): string { return $this->findingId; }
    public function actionType(): string { return $this->actionType; }
    public function description(): string { return $this->description; }

    public function edit(
        string $actionType,
        string $description,
        ?string $responsibleId,
        ?DateTimeImmutable $dueDate
    ): void {
        $this->actionType    = $actionType;
        $this->description   = $description;
        $this->responsibleId = $responsibleId;
        $this->dueDate       = $dueDate;
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'finding_id'     => $this->findingId,
            'action_type'    => $this->actionType,
            'description'    => $this->description,
            'responsible_id' => $this->responsibleId,
            'due_date'       => $this->dueDate?->format('Y-m-d'),
            'completed_at'   => $this->completedAt?->format('Y-m-d H:i:s'),
            'effectiveness'  => $this->effectiveness,
            'created_at'     => $this->createdAt->format(\DATE_ATOM),
        ];
    }
}