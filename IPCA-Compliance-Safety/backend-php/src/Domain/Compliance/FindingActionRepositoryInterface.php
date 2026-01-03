<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Domain\Compliance;

interface FindingActionRepositoryInterface
{
    public function save(FindingAction $action): void;

    /**
     * @return FindingAction[]
     */
    public function findByFindingId(string $findingId): array;

    public function findById(int $id): ?FindingAction;

    public function update(FindingAction $action): void;
	
	public function updateById(int $id, array $fields): void;
}