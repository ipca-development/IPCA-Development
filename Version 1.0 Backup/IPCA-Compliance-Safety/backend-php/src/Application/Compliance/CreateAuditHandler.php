<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Application\Compliance;

use IPCA\SafetyCompliance\Domain\Compliance\Audit;
use IPCA\SafetyCompliance\Domain\Compliance\AuditRepositoryInterface;

final class CreateAuditHandler
{
    public function __construct(
        private AuditRepositoryInterface $auditRepo
    ) {}

    public function handle(
        string $title,
        string $authority,
        string $auditType,
        ?string $externalRef,
        ?string $subject,
        string $createdBy
    ): Audit {
        $audit = Audit::create(
            title: $title,
            authority: $authority,
            auditType: $auditType,
            externalRef: $externalRef,
            subject: $subject,
            createdBy: $createdBy
        );

        $this->auditRepo->save($audit);

        return $audit;
    }
}