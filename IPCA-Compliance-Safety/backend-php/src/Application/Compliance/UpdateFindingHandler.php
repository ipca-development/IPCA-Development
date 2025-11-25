<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Application\Compliance;

use DateTimeImmutable;
use IPCA\SafetyCompliance\Domain\Compliance\FindingRepositoryInterface;

final class UpdateFindingHandler
{
    public function __construct(
        private FindingRepositoryInterface $findingRepo
    ) {}

    public function handle(
        string $findingId,
        string $reference,
        string $title,
        string $classification,
        string $severity,
        string $description,
        ?string $regulationRef,
        ?int $domainId,
        ?string $targetDateString
    ): void {
        $finding = $this->findingRepo->findById($findingId);
        if (!$finding) {
            throw new \RuntimeException('Finding not found');
        }

        $targetDate = $targetDateString
            ? new DateTimeImmutable($targetDateString)
            : null;

        $finding->edit(
            reference:      $reference,
            title:          $title,
            classification: $classification,
            severity:       $severity,
            description:    $description,
            regulationRef:  $regulationRef,
            domainId:       $domainId,
            targetDate:     $targetDate
        );

        $this->findingRepo->update($finding);
    }
}