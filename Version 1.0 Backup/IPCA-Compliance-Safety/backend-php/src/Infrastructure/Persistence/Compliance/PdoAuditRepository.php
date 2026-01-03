<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance;

use DateTimeImmutable;
use IPCA\SafetyCompliance\Domain\Compliance\Audit;
use IPCA\SafetyCompliance\Domain\Compliance\AuditRepositoryInterface;
use PDO;

final class PdoAuditRepository implements AuditRepositoryInterface
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

    public function save(Audit $audit): void
    {
        $sql = "
            INSERT INTO audits 
            (id, external_ref, title, authority, audit_type, status, start_date, end_date, closed_date,
             subject, created_by, created_at, updated_at)
            VALUES 
            (:id, :external_ref, :title, :authority, :audit_type, :status, :start_date, :end_date, :closed_date,
             :subject, :created_by, :created_at, :updated_at)
        ";

        $stmt = $this->pdo->prepare($sql);

        $data = $audit->toArray();
        $stmt->execute([
            ':id'          => $this->uuidToBin($data['id']),
            ':external_ref'=> $data['external_ref'],
            ':title'       => $data['title'],
            ':authority'   => $data['authority'],
            ':audit_type'  => $data['audit_type'],
            ':status'      => $data['status'],
            ':start_date'  => null,
            ':end_date'    => null,
            ':closed_date' => null,
            ':subject'     => $data['subject'],
            ':created_by'  => $this->uuidToBin($data['created_by']),
            ':created_at'  => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ':updated_at'  => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);
    }

    public function findAll(): array
    {
        $stmt = $this->pdo->query("
            SELECT id, external_ref, title, authority, audit_type, status,
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
                authority: $row['authority'],
                auditType: $row['audit_type'],
                status: $row['status'],
                startDate: null,
                endDate: null,
                closedDate: null,
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
            SELECT id, external_ref, title, authority, audit_type, status,
                   subject, created_by, created_at, updated_at
            FROM audits
            WHERE id = :id
        ");
        $stmt->execute([':id' => $this->uuidToBin($id)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new Audit(
            id: $this->binToUuid($row['id']),
            externalRef: $row['external_ref'],
            title: $row['title'],
            authority: $row['authority'],
            auditType: $row['audit_type'],
            status: $row['status'],
            startDate: null,
            endDate: null,
            closedDate: null,
            subject: $row['subject'],
            createdBy: $this->binToUuid($row['created_by']),
            createdAt: new DateTimeImmutable($row['created_at']),
            updatedAt: new DateTimeImmutable($row['updated_at'])
        );
    }
}