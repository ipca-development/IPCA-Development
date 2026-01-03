<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance;

use DateTimeImmutable;
use IPCA\SafetyCompliance\Domain\Compliance\FindingAction;
use IPCA\SafetyCompliance\Domain\Compliance\FindingActionRepositoryInterface;
use PDO;

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

        // Normalize due_date: never insert empty string into DATE column
        $dueDate = ($data['due_date'] ?? null);
        if ($dueDate === '') {
            $dueDate = null;
        }

        $sql = "
            INSERT INTO finding_actions
            (finding_id, action_type, description, responsible_id, due_date, completed_at, effectiveness, created_at)
            VALUES
            (:finding_id, :action_type, :description, :responsible_id, :due_date, :completed_at, :effectiveness, :created_at)
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':finding_id'     => $this->uuidToBin($data['finding_id']),
            ':action_type'    => $data['action_type'],
            ':description'    => $data['description'],
            ':responsible_id' => ($data['responsible_id'] ?? null) ?: null,
            ':due_date'       => $dueDate,
            ':completed_at'   => null,
            ':effectiveness'  => 'NOT_EVALUATED',
            ':created_at'     => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByFindingId(string $findingId): array
    {
        $sql = "
            SELECT id, finding_id, action_type, description, responsible_id,
                   due_date, completed_at, effectiveness, created_at
            FROM finding_actions
            WHERE finding_id = :finding_id
            ORDER BY created_at ASC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':finding_id' => $this->uuidToBin($findingId)]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $list = [];
        foreach ($rows as $row) {
            $list[] = new FindingAction(
                id: (int)$row['id'],
                findingId: $this->binToUuid($row['finding_id']),
                actionType: $row['action_type'],
                description: $row['description'],
                responsibleId: $row['responsible_id'],
                dueDate: $row['due_date'] ? new DateTimeImmutable($row['due_date']) : null,
                completedAt: $row['completed_at'] ? new DateTimeImmutable($row['completed_at']) : null,
                effectiveness: $row['effectiveness'],
                createdAt: new DateTimeImmutable($row['created_at'])
            );
        }

        return $list;
    }

    public function findById(int $id): ?FindingAction
    {
        $sql = "
            SELECT id, finding_id, action_type, description, responsible_id,
                   due_date, completed_at, effectiveness, created_at
            FROM finding_actions
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new FindingAction(
            id: (int)$row['id'],
            findingId: $this->binToUuid($row['finding_id']),
            actionType: $row['action_type'],
            description: $row['description'],
            responsibleId: $row['responsible_id'],
            dueDate: $row['due_date'] ? new DateTimeImmutable($row['due_date']) : null,
            completedAt: $row['completed_at'] ? new DateTimeImmutable($row['completed_at']) : null,
            effectiveness: $row['effectiveness'],
            createdAt: new DateTimeImmutable($row['created_at'])
        );
    }

    public function update(FindingAction $action): void
    {
        $data = $action->toArray();

        $dueDate = ($data['due_date'] ?? null);
        if ($dueDate === '') {
            $dueDate = null;
        }

        $sql = "
            UPDATE finding_actions SET
              action_type    = :action_type,
              description    = :description,
              responsible_id = :responsible_id,
              due_date       = :due_date
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':action_type'    => $data['action_type'],
            ':description'    => $data['description'],
            ':responsible_id' => ($data['responsible_id'] ?? null) ?: null,
            ':due_date'       => $dueDate,
            ':id'             => $data['id'],
        ]);
    }
	
	public function updateById(int $id, array $fields): void
{
    $allowed = ['action_type', 'description', 'responsible_id', 'due_date'];
    $set = [];
    $params = [':id' => $id];

    foreach ($allowed as $k) {
        if (array_key_exists($k, $fields)) {
            $set[] = "{$k} = :{$k}";
            $params[":{$k}"] = $fields[$k] === '' ? null : $fields[$k];
        }
    }

    if (!$set) return;

    $sql = "UPDATE finding_actions SET " . implode(', ', $set) . " WHERE id = :id";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
}
	
}