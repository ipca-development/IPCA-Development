<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Application\Compliance;

use DateTimeImmutable;
use IPCA\SafetyCompliance\Domain\Compliance\FindingActionRepositoryInterface;

final class UpdateActionHandler
{
    public function __construct(
        private FindingActionRepositoryInterface $actionRepo
    ) {}

    public function handle(
        int $actionId,
        string $actionType,
        string $description,
        ?string $responsibleId,
        ?string $dueDateString
    ): void {
        $action = $this->actionRepo->findById($actionId);
        if (!$action) {
            throw new \RuntimeException('Action not found');
        }

        $dueDate = $dueDateString
            ? new DateTimeImmutable($dueDateString)
            : null;

        $action->edit(
            actionType:    $actionType,
            description:   $description,
            responsibleId: $responsibleId,
            dueDate:       $dueDate
        );

        $this->actionRepo->update($action);
    }
}