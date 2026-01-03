<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Application\Compliance;

use IPCA\SafetyCompliance\Domain\Compliance\RootCauseAnalysis;
use IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance\PdoRcaRepository;

final class RecordRcaHandler
{
    public function __construct(
        private PdoRcaRepository $rcaRepo  // could be an interface later
    ) {}

    public function handle(
        string $findingId,
        string $why1,
        ?string $why2,
        ?string $why3,
        ?string $why4,
        ?string $why5,
        ?string $rootCause,
        ?string $preventiveTheme,
        string $createdBy
    ): RootCauseAnalysis {
        $rca = RootCauseAnalysis::create(
            findingId: $findingId,
            why1: $why1,
            why2: $why2,
            why3: $why3,
            why4: $why4,
            why5: $why5,
            rootCause: $rootCause,
            preventiveTheme: $preventiveTheme,
            createdBy: $createdBy
        );

        $this->rcaRepo->save($rca);

        return $rca;
    }
}