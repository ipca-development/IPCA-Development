<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Domain\Compliance;

interface FindingRepositoryInterface
{
    public function save(Finding $finding): void;

    /**
     * @return Finding[]
     */
    public function findByAudit(string $auditId): array;

    public function findById(string $id): ?Finding;

    public function update(Finding $finding): void;
}