<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Http\Controllers;

use IPCA\SafetyCompliance\Application\Compliance\CreateAuditHandler;
use IPCA\SafetyCompliance\Domain\Compliance\AuditRepositoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuditController
{
    public function __construct(
        private CreateAuditHandler $createAuditHandler,
        private AuditRepositoryInterface $auditRepo
    ) {}

    public function create(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();

        // Later this will come from auth middleware
        $createdBy = $data['created_by'] ?? '00000000-0000-0000-0000-000000000001';

        $audit = $this->createAuditHandler->handle(
            title:       $data['title'] ?? 'Untitled audit',
            authority:   $data['authority'] ?? 'INTERNAL',
            auditType:   $data['audit_type'] ?? 'CMS',
            externalRef: $data['external_ref'] ?? null,
            subject:     $data['subject'] ?? null,
            createdBy:   $createdBy
        );

        $response->getBody()->write(json_encode($audit->toArray()));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(201);
    }

    public function list(Request $request, Response $response): Response
    {
        $audits = $this->auditRepo->findAll();
        $data = array_map(fn($a) => $a->toArray(), $audits);

        $response->getBody()->write(json_encode($data));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function detail(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];

        $audit = $this->auditRepo->findById($id);
        if (!$audit) {
            $response->getBody()->write(json_encode(['error' => 'Audit not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($audit->toArray()));
        return $response->withHeader('Content-Type', 'application/json');
    }
}