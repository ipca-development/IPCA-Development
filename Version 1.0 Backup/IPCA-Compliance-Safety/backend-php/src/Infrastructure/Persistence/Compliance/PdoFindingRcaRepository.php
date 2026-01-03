<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance;

use DateTimeImmutable;
use PDO;

final class PdoFindingRcaRepository
{
    public function __construct(private PDO $pdo) {}

    private function uuidToBin(string $uuid): string
    {
        return hex2bin(str_replace('-', '', $uuid));
    }

    private function binToUuid(string $bin): string
    {
        $hex = bin2hex($bin);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split($hex, 4));
    }

    public function upsert(string $findingId, array $steps, ?string $createdByUuid = null): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $sql = "
            INSERT INTO finding_rca (finding_id, steps_json, created_by, created_at, updated_at)
            VALUES (:finding_id, :steps_json, :created_by, :created_at, :updated_at)
            ON DUPLICATE KEY UPDATE
              steps_json = VALUES(steps_json),
              created_by = VALUES(created_by),
              updated_at = VALUES(updated_at)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':finding_id' => $this->uuidToBin($findingId),
            ':steps_json' => json_encode($steps, JSON_UNESCAPED_UNICODE),
            ':created_by' => $createdByUuid ? $this->uuidToBin($createdByUuid) : null,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);
    }

    public function findSteps(string $findingId): ?array
    {
        $sql = "SELECT steps_json FROM finding_rca WHERE finding_id = :finding_id LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':finding_id' => $this->uuidToBin($findingId)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        $steps = json_decode($row['steps_json'], true);
        return is_array($steps) ? $steps : null;
    }
}