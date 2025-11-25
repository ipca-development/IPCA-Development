<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Application\Compliance;

use DateTimeImmutable;
use IPCA\SafetyCompliance\Domain\Compliance\Finding;
use IPCA\SafetyCompliance\Domain\Compliance\FindingRepositoryInterface;

final class AddFindingHandler
{
    public function __construct(
        private FindingRepositoryInterface $findingRepo
    ) {}

    public function handle(
        string $auditId,
        string $reference,
        string $title,
        string $classification,
        string $severity,
        string $description,
        ?string $regulationRef,
        ?int $domainId,
        ?string $targetDateString
    ): Finding {
        $targetDate = $targetDateString ? new DateTimeImmutable($targetDateString) : null;

        $finding = Finding::create(
            auditId: $auditId,
            reference: $reference,
            title: $title,
            classification: $classification,
            severity: $severity,
            description: $description,
            regulationRef: $regulationRef,
            domainId: $domainId,
            targetDate: $targetDate
        );

        $this->findingRepo->save($finding);

        return $finding;
    }
}