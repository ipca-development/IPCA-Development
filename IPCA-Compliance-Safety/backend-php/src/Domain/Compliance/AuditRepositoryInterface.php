<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Domain\Compliance;

interface AuditRepositoryInterface
{
    public function save(Audit $audit): void;

    /**
     * @return Audit[]
     */
    public function findAll(): array;

    public function findById(string $id): ?Audit;
}