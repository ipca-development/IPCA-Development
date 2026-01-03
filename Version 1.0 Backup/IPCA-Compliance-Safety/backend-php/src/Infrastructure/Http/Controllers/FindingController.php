<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Http\Controllers;

use IPCA\SafetyCompliance\Application\Compliance\AddActionHandler;
use IPCA\SafetyCompliance\Application\Compliance\AddFindingHandler;
use IPCA\SafetyCompliance\Domain\Compliance\FindingActionRepositoryInterface;
use IPCA\SafetyCompliance\Domain\Compliance\FindingRepositoryInterface;
use IPCA\SafetyCompliance\Infrastructure\Ai\RcaAiService;
use IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance\PdoFindingRcaRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class FindingController
{
    private AddFindingHandler $addFindingHandler;
    private AddActionHandler $addActionHandler;
    private FindingRepositoryInterface $findingRepo;
    private FindingActionRepositoryInterface $actionRepo;

    // AI RCA
    private RcaAiService $rcaAi;
    private PdoFindingRcaRepository $rcaRepo;

    public function __construct(
        AddFindingHandler $addFindingHandler,
        AddActionHandler $addActionHandler,
        FindingRepositoryInterface $findingRepo,
        FindingActionRepositoryInterface $actionRepo,
        RcaAiService $rcaAi,
        PdoFindingRcaRepository $rcaRepo
    ) {
        $this->addFindingHandler = $addFindingHandler;
        $this->addActionHandler  = $addActionHandler;
        $this->findingRepo       = $findingRepo;
        $this->actionRepo        = $actionRepo;
        $this->rcaAi             = $rcaAi;
        $this->rcaRepo           = $rcaRepo;
    }

    /**
     * Dashboard: GET /compliance/findings?status=open
     */
    public function listAll(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $status = $params['status'] ?? null;

        $findings = $this->findingRepo->findAll($status);

        $data = array_map(fn($f) => $f->toArray(), $findings);
        $response->getBody()->write(json_encode($data));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * GET /compliance/audits/{id}/findings
     */
    public function listForAudit(Request $request, Response $response, array $args): Response
    {
        $auditId = $args['id'];

        $findings = $this->findingRepo->findByAudit($auditId);
        $data = array_map(fn($f) => $f->toArray(), $findings);

        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /compliance/audits/{id}/findings
     */
    public function createForAudit(Request $request, Response $response, array $args): Response
    {
        $auditId = $args['id'];
        $data = (array)$request->getParsedBody();

        $finding = $this->addFindingHandler->handle(
            auditId: $auditId,
            reference: $data['reference'] ?? '',
            title: $data['title'] ?? '',
            classification: $data['classification'] ?? 'LEVEL_2',
            severity: $data['severity'] ?? 'MEDIUM',
            description: $data['description'] ?? '',
            regulationRef: $data['regulation_ref'] ?? null,
            domainId: isset($data['domain_id']) ? (int)$data['domain_id'] : null,
            targetDateString: $data['target_date'] ?? null
        );

        $response->getBody()->write(json_encode($finding->toArray()));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * PATCH /compliance/findings/{id}
     * Used by B2 edit from the modal.
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $findingId = $args['id'];
        $data = (array)$request->getParsedBody();

        $payload = [
            'reference'      => $data['reference'] ?? null,
            'title'          => $data['title'] ?? null,
            'classification' => $data['classification'] ?? null,
            'severity'       => $data['severity'] ?? null,
            'description'    => $data['description'] ?? null,
            'regulation_ref' => array_key_exists('regulation_ref', $data) ? $data['regulation_ref'] : null,
            'domain_id'      => isset($data['domain_id']) ? (int)$data['domain_id'] : null,
            'target_date'    => array_key_exists('target_date', $data) ? $data['target_date'] : null,
            'status'         => $data['status'] ?? null,
        ];

        $this->findingRepo->updateById($findingId, $payload);

        $updated = $this->findingRepo->findById($findingId);
        $response->getBody()->write(json_encode($updated ? $updated->toArray() : ['status' => 'ok']));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /compliance/findings/{id}/actions
     * Create CAP action item (existing handler).
     */
    public function addAction(Request $request, Response $response, array $args): Response
    {
        $findingId = $args['id'];
        $data = (array)$request->getParsedBody();

        // If UI sends "option" (A/B/C), you can later expand this method.
        // For now, just accept a simple action format if present.
        if (isset($data['action_type']) || isset($data['description'])) {
            $this->addActionHandler->handle(
                findingId: $findingId,
                actionType: $data['action_type'] ?? 'CORRECTIVE',
                description: $data['description'] ?? '',
                responsibleId: $data['responsible_id'] ?? null,
                dueDateString: $data['due_date'] ?? null
            );
        }

        $response->getBody()->write(json_encode(['status' => 'ok', 'message' => 'Action created']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    /**
     * GET /compliance/findings/{id}/actions
     */
    public function listActions(Request $request, Response $response, array $args): Response
    {
        $findingId = $args['id'];
        $actions = $this->actionRepo->findByFindingId($findingId);

        $data = array_map(fn($a) => $a->toArray(), $actions);
        $response->getBody()->write(json_encode($data));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /compliance/findings/{id}/rca/next-step
     * AI generates next Why step based on current steps.
     */
    public function rcaNextStep(Request $request, Response $response, array $args): Response
    {
        $findingId = $args['id'];
        $data = (array)$request->getParsedBody();
        $steps = $data['steps'] ?? [];
        if (!is_array($steps)) $steps = [];

        $finding = $this->findingRepo->findById($findingId);
        if (!$finding) {
            $response->getBody()->write(json_encode(['error' => 'Finding not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $findingArr = $finding->toArray();

        $next = $this->rcaAi->generateNextStep($findingArr, $steps);

        $response->getBody()->write(json_encode($next));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /compliance/findings/{id}/rca
     * Save full RCA chain (steps array).
     */
   public function saveRca(Request $request, Response $response, array $args): Response
{
    $findingId = $args['id'];
    $data = (array)$request->getParsedBody();
    $steps = $data['steps'] ?? [];

    if (!is_array($steps)) {
        $response->getBody()->write(json_encode(['error' => 'Invalid steps']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    // TEMP dev user until auth exists
    $createdBy = '415712F4-C7D8-11F0-AD9A-84068FBD07E7';

    $this->rcaRepo->upsert($findingId, $steps, $createdBy);

    $response->getBody()->write(json_encode(['status' => 'ok']));
    return $response->withHeader('Content-Type', 'application/json');
}

    /**
     * POST /compliance/findings/{id}/actions/suggest-ai
     * Stub for Step D (CAP AI). Returns empty options for now.
     */
    
	
	public function suggestCap(Request $request, Response $response, array $args): Response
{
    try {
        $findingId = $args['id'];

        $finding = $this->findingRepo->findById($findingId);
        if (!$finding) {
            $response->getBody()->write(json_encode(['error' => 'Finding not found', 'options' => []]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // Load saved RCA steps
        $steps = $this->rcaRepo->findSteps($findingId);
        if (!is_array($steps) || count($steps) === 0) {
            $response->getBody()->write(json_encode([
                'error' => 'No saved RCA steps found. Save RCA first.',
                'options' => []
            ]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $findingArr = $finding->toArray();

        $findingText =
            "Reference: " . ($findingArr['reference'] ?? '') . "\n" .
            "Title: " . ($findingArr['title'] ?? '') . "\n" .
            "Classification: " . ($findingArr['classification'] ?? '') . "\n" .
            "Severity: " . ($findingArr['severity'] ?? '') . "\n" .
            "Regulation ref: " . ($findingArr['regulation_ref'] ?? '') . "\n" .
            "Description:\n" . ($findingArr['description'] ?? '') . "\n";

        $rcaText = "";
        foreach ($steps as $s) {
            $n = (int)($s['whyNumber'] ?? 0);
            $q = trim((string)($s['question'] ?? ''));
            $a = trim((string)($s['answer'] ?? ''));
            if ($n >= 1 && $n <= 5) {
                $rcaText .= "WHY {$n} QUESTION: {$q}\nWHY {$n} ANSWER (final): {$a}\n\n";
            }
        }

        $prompt = <<<TXT
You are an aviation compliance expert. Create a proposed Corrective Action Plan (CAP) for an audit finding.

You must propose THREE options with increasing robustness:
- Option A (QUICK): quick and easy, low effort, limited scope.
- Option B (RECOMMENDED): balanced effort and strong compliance improvement.
- Option C (BEST): most robust systemic solution, more time and effort.

Rules:
- Actions must be realistic for an ATO/flight training organisation.
- Do not blame individuals; focus on process, training, oversight, documentation, tools.
- Each option must include 2–3 concrete action items ONLY.
- Each action item must include:
  - action_type: CORRECTIVE, PREVENTIVE, or CONTAINMENT
  - description: clear, authority-ready text
  - due_days: integer (e.g. 7 / 30 / 90)

Output MUST be valid JSON ONLY with:
{
  "options": [
    {
      "label":"Option A",
      "effort":"QUICK",
      "actions":[...]
    },
    {
      "label":"Option B",
      "effort":"RECOMMENDED",
      "actions":[...]
    },
    {
      "label":"Option C",
      "effort":"BEST",
      "actions":[...]
    }
  ]
}

FINDING CONTEXT:
{$findingText}

APPROVED RCA (5 Whys):
{$rcaText}
TXT;

        $decoded = $this->rcaAi->generateJson($prompt, 'gpt-4o-mini');

        $options = $decoded['options'] ?? [];
        if (!is_array($options)) $options = [];

        $response->getBody()->write(json_encode(['options' => $options]));
        return $response->withHeader('Content-Type', 'application/json');

    } catch (\Throwable $e) {
        $response->getBody()->write(json_encode([
            'error' => $e->getMessage(),
            'options' => []
        ]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
    }
}
	
	

    /**
     * GET /compliance/mccf
     * Stub: return empty list unless you already have MCCF table.
     */
    public function listMccfItems(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode([]));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /compliance/findings/{id}/manual-ref
     * Stub: accept and return OK (store later).
     */
    public function saveManualRef(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /compliance/findings/{id}/mccf-link
     * Stub: accept and return OK (store later).
     */
    public function saveMccfLink(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * POST /compliance/findings/{id}/notes
     * Stub: accept and return OK (store later).
     */
    public function saveNotes(Request $request, Response $response, array $args): Response
    {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    }
	
	public function getRca(Request $request, Response $response, array $args): Response
{
    $findingId = $args['id'];
    $steps = $this->rcaRepo->findSteps($findingId);

    $response->getBody()->write(json_encode([
        'steps' => $steps ?? []
    ]));
    return $response->withHeader('Content-Type', 'application/json');
}
}