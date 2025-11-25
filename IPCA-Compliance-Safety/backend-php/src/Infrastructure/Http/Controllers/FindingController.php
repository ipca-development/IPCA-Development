<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Http\Controllers;

use IPCA\SafetyCompliance\Application\Compliance\AddActionHandler;
use IPCA\SafetyCompliance\Application\Compliance\AddFindingHandler;
use IPCA\SafetyCompliance\Application\Compliance\RecordRcaHandler;
use IPCA\SafetyCompliance\Application\Compliance\UpdateActionHandler;
use IPCA\SafetyCompliance\Application\Compliance\UpdateFindingHandler;
use IPCA\SafetyCompliance\Domain\Compliance\FindingActionRepositoryInterface;
use IPCA\SafetyCompliance\Domain\Compliance\FindingRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class FindingController
{
    public function __construct(
        private AddFindingHandler $addFindingHandler,
        private AddActionHandler $addActionHandler,
        private RecordRcaHandler $recordRcaHandler,
        private UpdateFindingHandler $updateFindingHandler,
        private UpdateActionHandler $updateActionHandler,
        private FindingRepositoryInterface $findingRepo,
        private FindingActionRepositoryInterface $actionRepo
    ) {}

    /**
     * Create a new finding under an audit
     */
    public function createForAudit(Request $request, Response $response, array $args): Response
    {
        $auditId = $args['id'];
        $data    = (array)$request->getParsedBody();

        $finding = $this->addFindingHandler->handle(
            auditId:        $auditId,
            reference:      $data['reference']      ?? '',
            title:          $data['title']          ?? '',
            classification: $data['classification'] ?? 'LEVEL_2',
            severity:       $data['severity']       ?? 'MEDIUM',
            description:    $data['description']    ?? '',
            regulationRef:  $data['regulation_ref'] ?? null,
            domainId:       isset($data['domain_id']) ? (int)$data['domain_id'] : null,
            targetDateString: $data['target_date']  ?? null
        );

        $response->getBody()->write(json_encode($finding->toArray()));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(201);
    }

    /**
     * List findings for an audit
     */
    public function listForAudit(Request $request, Response $response, array $args): Response
    {
        $auditId  = $args['id'];
        $findings = $this->findingRepo->findByAudit($auditId);

        $data = array_map(fn($f) => $f->toArray(), $findings);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Update an existing finding
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $findingId = $args['id'];
        $data      = (array)$request->getParsedBody();

        $this->updateFindingHandler->handle(
            findingId:      $findingId,
            reference:      $data['reference']      ?? '',
            title:          $data['title']          ?? '',
            classification: $data['classification'] ?? 'LEVEL_2',
            severity:       $data['severity']       ?? 'MEDIUM',
            description:    $data['description']    ?? '',
            regulationRef:  $data['regulation_ref'] ?? null,
            domainId:       isset($data['domain_id']) ? (int)$data['domain_id'] : null,
            targetDateString: $data['target_date']  ?? null
        );

        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Add a new action for a finding
     */
    public function addAction(Request $request, Response $response, array $args): Response
    {
        $findingId = $args['id'];
        $data      = (array)$request->getParsedBody();

        $this->addActionHandler->handle(
            findingId:     $findingId,
            actionType:    $data['action_type']    ?? 'CORRECTIVE',
            description:   $data['description']    ?? '',
            responsibleId: $data['responsible_id'] ?? null,
            dueDateString: $data['due_date']       ?? null
        );

        $response->getBody()->write(json_encode([
            'status'  => 'ok',
            'message' => 'Action created',
        ]));

        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(201);
    }

    /**
     * List actions for a finding
     */
    public function listActions(Request $request, Response $response, array $args): Response
    {
        $findingId = $args['id'];
        $actions   = $this->actionRepo->findByFindingId($findingId);

        $data = array_map(fn($a) => $a->toArray(), $actions);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Update an existing action
     */
    public function updateAction(Request $request, Response $response, array $args): Response
    {
        $findingId = $args['id'];      // path param, not used in logic
        $actionId  = (int)$args['actionId'];
        $data      = (array)$request->getParsedBody();

        $this->updateActionHandler->handle(
            actionId:      $actionId,
            actionType:    $data['action_type']    ?? 'CORRECTIVE',
            description:   $data['description']    ?? '',
            responsibleId: $data['responsible_id'] ?? null,
            dueDateString: $data['due_date']       ?? null
        );

        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Record RCA (5-Whys) for a finding
     */
    public function recordRca(Request $request, Response $response, array $args): Response
    {
        $findingId = $args['id'];
        $data      = (array)$request->getParsedBody();

        $createdBy = $data['created_by'] ?? '415712F4-C7D8-11F0-AD9A-84068FBD07E7';

        $rca = $this->recordRcaHandler->handle(
            findingId:       $findingId,
            why1:            $data['why1']            ?? '',
            why2:            $data['why2']            ?? null,
            why3:            $data['why3']            ?? null,
            why4:            $data['why4']            ?? null,
            why5:            $data['why5']            ?? null,
            rootCause:       $data['root_cause']      ?? null,
            preventiveTheme: $data['preventive_theme']?? null,
            createdBy:       $createdBy
        );

        $response->getBody()->write(json_encode($rca->toArray()));
        return $response->withHeader('Content-Type', 'application/json')
                        ->withStatus(201);
    }
}