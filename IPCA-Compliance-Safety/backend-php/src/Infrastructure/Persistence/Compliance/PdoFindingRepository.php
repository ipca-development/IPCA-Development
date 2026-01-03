<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance;

use DateTimeImmutable;
use IPCA\SafetyCompliance\Domain\Compliance\Finding;
use IPCA\SafetyCompliance\Domain\Compliance\FindingRepositoryInterface;
use PDO;

final class PdoFindingRepository implements FindingRepositoryInterface
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

    public function save(Finding $finding): void
    {
        $data = $finding->toArray();

        $sql = "
            INSERT INTO findings 
            (id, audit_id, reference, title, classification, status, severity, description,
             regulation_ref, raised_date, target_date, closed_date, domain_id, created_at, updated_at)
            VALUES
            (:id, :audit_id, :reference, :title, :classification, :status, :severity, :description,
             :regulation_ref, :raised_date, :target_date, :closed_date, :domain_id, :created_at, :updated_at)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'             => $this->uuidToBin($data['id']),
            ':audit_id'       => $this->uuidToBin($data['audit_id']),
            ':reference'      => $data['reference'],
            ':title'          => $data['title'],
            ':classification' => $data['classification'],
            ':status'         => $data['status'],
            ':severity'       => $data['severity'],
            ':description'    => $data['description'],
            ':regulation_ref' => $data['regulation_ref'],
            ':raised_date'    => $data['raised_date'],
            ':target_date'    => $data['target_date'],
            ':closed_date'    => $data['closed_date'],
            ':domain_id'      => $data['domain_id'],
            ':created_at'     => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ':updated_at'     => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function findByAudit(string $auditId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT *
            FROM findings
            WHERE audit_id = :audit_id
            ORDER BY created_at ASC
        ");
        $stmt->execute([':audit_id' => $this->uuidToBin($auditId)]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $list = [];
        foreach ($rows as $row) {
            $list[] = new Finding(
                id: $this->binToUuid($row['id']),
                auditId: $this->binToUuid($row['audit_id']),
                reference: $row['reference'],
                title: $row['title'],
                classification: $row['classification'],
                status: $row['status'],
                severity: $row['severity'],
                description: $row['description'],
                regulationRef: $row['regulation_ref'],
                raisedDate: new DateTimeImmutable($row['raised_date']),
                targetDate: $row['target_date'] ? new DateTimeImmutable($row['target_date']) : null,
                closedDate: $row['closed_date'] ? new DateTimeImmutable($row['closed_date']) : null,
                domainId: $row['domain_id'],
                createdAt: new DateTimeImmutable($row['created_at']),
                updatedAt: new DateTimeImmutable($row['updated_at'])
            );
        }

        return $list;
    }

    public function findById(string $id): ?Finding
    {
        $stmt = $this->pdo->prepare("SELECT * FROM findings WHERE id = :id");
        $stmt->execute([':id' => $this->uuidToBin($id)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new Finding(
            id: $this->binToUuid($row['id']),
            auditId: $this->binToUuid($row['audit_id']),
            reference: $row['reference'],
            title: $row['title'],
            classification: $row['classification'],
            status: $row['status'],
            severity: $row['severity'],
            description: $row['description'],
            regulationRef: $row['regulation_ref'],
            raisedDate: new DateTimeImmutable($row['raised_date']),
            targetDate: $row['target_date'] ? new DateTimeImmutable($row['target_date']) : null,
            closedDate: $row['closed_date'] ? new DateTimeImmutable($row['closed_date']) : null,
            domainId: $row['domain_id'],
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at'])
        );
    }

    public function update(Finding $finding): void
    {
        $data = $finding->toArray();

        $sql = "
            UPDATE findings SET
              reference      = :reference,
              title          = :title,
              classification = :classification,
              severity       = :severity,
              description    = :description,
              regulation_ref = :regulation_ref,
              target_date    = :target_date,
              domain_id      = :domain_id,
              updated_at     = :updated_at
            WHERE id = :id
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':reference'      => $data['reference'],
            ':title'          => $data['title'],
            ':classification' => $data['classification'],
            ':severity'       => $data['severity'],
            ':description'    => $data['description'],
            ':regulation_ref' => $data['regulation_ref'],
            ':target_date'    => $data['target_date'],
            ':domain_id'      => $data['domain_id'],
            ':updated_at'     => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ':id'             => $this->uuidToBin($data['id']),
        ]);
    }
	
	public function findAll(?string $status = null): array
{
    $sql = "SELECT * FROM findings";
    $params = [];

    if ($status) {
        $sql .= " WHERE status = :status";
        $params[':status'] = strtoupper($status);
    }

    $sql .= " ORDER BY created_at DESC";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $list = [];
    foreach ($rows as $row) {
        $list[] = new Finding(
            id: $this->binToUuid($row['id']),
            auditId: $this->binToUuid($row['audit_id']),
            reference: $row['reference'],
            title: $row['title'],
            classification: $row['classification'],
            status: $row['status'],
            severity: $row['severity'],
            description: $row['description'],
            regulationRef: $row['regulation_ref'],
            raisedDate: new \DateTimeImmutable($row['raised_date']),
            targetDate: $row['target_date'] ? new \DateTimeImmutable($row['target_date']) : null,
            closedDate: $row['closed_date'] ? new \DateTimeImmutable($row['closed_date']) : null,
            domainId: $row['domain_id'],
            createdAt: new \DateTimeImmutable($row['created_at']),
            updatedAt: new \DateTimeImmutable($row['updated_at'])
        );
    }

    return $list;
}

public function updateById(string $id, array $fields): void
{
    // Only allow specific fields to be updated
    $allowed = [
    'reference',
    'title',
    'classification',
    'severity',
    'description',
    'regulation_ref',
    'domain_id',
    'target_date',
    'status',
    'cap_selected_option',
    'cap_selected_effort',
];

    $setParts = [];
    $params = [ ':id' => $this->uuidToBin($id) ];

    foreach ($allowed as $key) {
        if (array_key_exists($key, $fields) && $fields[$key] !== null) {
            $setParts[] = "$key = :$key";
            $params[":$key"] = $fields[$key];
        }
    }

    // target_date and regulation_ref can explicitly be set to NULL
    if (array_key_exists('target_date', $fields) && $fields['target_date'] === null) {
        $setParts[] = "target_date = NULL";
    }
    if (array_key_exists('regulation_ref', $fields) && $fields['regulation_ref'] === null) {
        $setParts[] = "regulation_ref = NULL";
    }

    if (empty($setParts)) {
        return; // nothing to update
    }

    $setParts[] = "updated_at = :updated_at";
    $params[':updated_at'] = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

    $sql = "UPDATE findings SET " . implode(', ', $setParts) . " WHERE id = :id";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
}	
	
	
}