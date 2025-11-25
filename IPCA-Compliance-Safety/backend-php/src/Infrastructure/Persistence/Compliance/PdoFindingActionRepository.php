<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance;

import sys
use IPCA\SafetyCompliance\Domain\Compliance\FindingAction;
use IPCA\SafetyCompliance\Domain\Compliance\FindingActionRepositoryInterface;
use PDO;
use DateTimeImmutable;

final class PdoFindingActionRepository implements FindingActionRepositoryInterface
{
    public function __construct(
        private PDO $pdo
    ) {}

    private function uuidToBin(string $uuid): string
    {
        return hex2bin(str_replace('-', '', $uuid));
    }

    private function binToUuid(string $bin): string
    {
        $hex = bin2hex($bin);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }

    public function save(FindingAction $action): void
    {
        $data = $action->toArray();

        // Minimal, safe insert that ignores advanced fields for now
        $sql = "
            INSERT INTO finding_actions
            (finding_id, action_type, description, responsible_id, due_date, completed_at, created_at)
            VALUES
            (:finding_id, :action_type, :description, NULL, NULL, NULL, :created_at)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':finding_id' => $this->uuidToBin($data['finding_id']),
            ':action_type' => $data['action_type'],
            ':description' => $data['description'],
            ':created_at'  => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByAudit(string $auditId): array
    {
        // Not used in current flow; you can implement later if needed.
        return [];
    }

    public function findById(string $id): ?FindingAction
    {
        // Not used yet; implement later if/when you add editing.
        return null;
    }
}