<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance;

use IPCA\SafetyCompliance\Domain\Compliance\RootCauseAnalysis;
use PDO;

final class PdoRcaRepository
{
    public function __construct(
        private PDO $pdo
    ) {}

    private function uuidToBin(string $uuid): string
    {
        return hex2bin(str_replace('-', '', $uuid));
    }

    public function save(RootCauseAnalysis $rca): void
    {
        $data = $rca->toArray();

        $sql = "
            INSERT INTO finding_rca
            (finding_id, why1, why2, why3, why4, why5, root_cause, preventive_theme,
             created_by, created_at)
            VALUES
            (:finding_id, :why1, :why2, :why3, :why4, :why5, :root_cause, :preventive_theme,
             :created_by, :created_at)
        ";

        $stmt = $this->pdo->prepare($sql);

        $stmt->execute([
            ':finding_id'      => $this->uuidToBin($data['finding_id']),
            ':why1'            => $data['why1'],
            ':why2'            => $data['why2'],
            ':why3'            => $data['why3'],
            ':why4'            => $data['why4'],
            ':why5'            => $data['why5'],
            ':root_cause'      => $data['root_cause'],
            ':preventive_theme'=> $data['preventive_theme'],
            ':created_by'      => $this->uuidToBin($data['created_by']),
            ':created_at'      => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }
}