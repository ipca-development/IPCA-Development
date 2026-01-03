<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Application\Compliance;

use DateTimeImmutable;
use IPCA\SafetyCompliance\Domain\Compliance\Audit;
use IPCA\SafetyCompliance\Domain\Compliance\AuditRepositoryInterface;

final class CreateAuditHandler
{
    public function __construct(
        private AuditRepositoryInterface $auditRepo
    ) {}

    public function handle(
    string $title,
    string $auditCategory,
    string $auditEntity,
    string $auditType,
    ?string $externalRef,
    ?string $subject,
    string $createdBy,
    ?string $startDate = null,
    ?string $endDate = null
): Audit {
    $audit = Audit::create(
        title: $title,
        auditCategory: $auditCategory,
        auditEntity: $auditEntity,
        auditType: $auditType,
        externalRef: $externalRef,
        subject: $subject,
        createdBy: $createdBy,
        startDate: $startDate,
        endDate: $endDate
    );

    $this->auditRepo->save($audit);
    return $audit;
}
}