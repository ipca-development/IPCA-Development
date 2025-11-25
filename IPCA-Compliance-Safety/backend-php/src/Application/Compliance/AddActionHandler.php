<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Application\Compliance;

use DateTimeImmutable;
use IPCA\SafetyCompliance\Domain\Compliance\FindingAction;
use IPCA\SafetyCompliance\Domain\Compliance\FindingActionRepositoryInterface;

final class AddActionHandler
{
    public function __construct(
        private FindingActionRepositoryInterface $actionRepo
    ) {}

    public function handle(
        string $findingId,
        string $actionType,
        string $description,
        ?string $responsibleId,
        ?string $dueDateString
    ): FindingAction {
        $dueDate = $dueDateString ? new DateTimeImmutable($dueDateString) : null;

        $action = FindingAction::create(
            findingId: $findingId,
            actionType: $actionType,
            description: $description,
            responsibleId: $responsibleId,
            dueDate: $dueDate
        );

        $this->actionRepo->save($action);

        return $action;
    }
}