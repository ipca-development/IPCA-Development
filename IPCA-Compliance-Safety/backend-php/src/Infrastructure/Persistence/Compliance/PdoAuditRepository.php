<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance;

use DateTimeImmutable;
use IPCA\SafetyCompliance\Domain\Compliance\Audit;
use IPCA\SafetyCompliance\Domain\Compliance\AuditRepositoryInterface;
use PDO;

final class PdoAuditRepository implements AuditRepositoryInterface
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

    private function dtOrNull(?string $ymd): ?DateTimeImmutable
    {
        if (!$ymd) return null;
        return new DateTimeImmutable($ymd);
    }

    public function save(Audit $audit): void
    {
        $data = $audit->toArray();
        $now  = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $sql = "
            INSERT INTO audits
            (id, external_ref, title, audit_category, audit_entity, audit_type, status,
             start_date, end_date, closed_date, subject, created_by, created_at, updated_at)
            VALUES
            (:id, :external_ref, :title, :audit_category, :audit_entity, :audit_type, :status,
             :start_date, :end_date, :closed_date, :subject, :created_by, :created_at, :updated_at)
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':id'            => $this->uuidToBin($data['id']),
            ':external_ref'  => $data['external_ref'],
            ':title'         => $data['title'],
            ':audit_category'=> $data['audit_category'],
            ':audit_entity'  => $data['audit_entity'],
            ':audit_type'    => $data['audit_type'],
            ':status'        => $data['status'],
            ':start_date'    => $data['start_date'],
            ':end_date'      => $data['end_date'],
            ':closed_date'   => $data['closed_date'],
            ':subject'       => $data['subject'],
            ':created_by'    => $this->uuidToBin($data['created_by']),
            ':created_at'    => $now,
            ':updated_at'    => $now,
        ]);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT
              id, external_ref, title, audit_category, audit_entity, audit_type, status,
              start_date, end_date, closed_date,
              subject, created_by, created_at, updated_at
            FROM audits
            ORDER BY created_at DESC
        ");

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $audits = [];

        foreach ($rows as $row) {
            $audits[] = new Audit(
                id: $this->binToUuid($row['id']),
                externalRef: $row['external_ref'],
                title: $row['title'],
                auditCategory: $row['audit_category'] ?: 'CAA',
                auditEntity: $row['audit_entity'] ?: 'UNKNOWN',
                auditType: $row['audit_type'],
                status: $row['status'],
                startDate: $this->dtOrNull($row['start_date']),
                endDate: $this->dtOrNull($row['end_date']),
                closedDate: $this->dtOrNull($row['closed_date']),
                subject: $row['subject'],
                createdBy: $this->binToUuid($row['created_by']),
                createdAt: new DateTimeImmutable($row['created_at']),
                updatedAt: new DateTimeImmutable($row['updated_at'])
            );
        }

        return $audits;
    }

    public function findById(string $id): ?Audit
    {
        $stmt = $this->pdo->prepare("
            SELECT
              id, external_ref, title, audit_category, audit_entity, audit_type, status,
              start_date, end_date, closed_date,
              subject, created_by, created_at, updated_at
            FROM audits
            WHERE id = :id
        ");
        $stmt->execute([':id' => $this->uuidToBin($id)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        return new Audit(
            id: $this->binToUuid($row['id']),
            externalRef: $row['external_ref'],
            title: $row['title'],
            auditCategory: $row['audit_category'] ?: 'CAA',
            auditEntity: $row['audit_entity'] ?: 'UNKNOWN',
            auditType: $row['audit_type'],
            status: $row['status'],
            startDate: $this->dtOrNull($row['start_date']),
            endDate: $this->dtOrNull($row['end_date']),
            closedDate: $this->dtOrNull($row['closed_date']),
            subject: $row['subject'],
            createdBy: $this->binToUuid($row['created_by']),
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at'])
        );
    }

    public function updateById(string $id, array $fields): void
    {
        $allowed = [
            'external_ref','title','audit_category','audit_entity','audit_type','status',
            'subject','start_date','end_date','closed_date'
        ];

        $set = [];
        $params = [':id' => $this->uuidToBin($id)];

        foreach ($allowed as $k) {
            if (array_key_exists($k, $fields)) {
                $set[] = "{$k} = :{$k}";
                $params[":{$k}"] = ($fields[$k] === '') ? null : $fields[$k];
            }
        }

        if (!$set) return;

        $set[] = "updated_at = :updated_at";
        $params[':updated_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $sql = "UPDATE audits SET " . implode(', ', $set) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
    }
}