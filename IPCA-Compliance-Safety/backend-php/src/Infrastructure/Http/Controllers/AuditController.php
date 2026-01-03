<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Http\Controllers;

use Dompdf\Dompdf;
use IPCA\SafetyCompliance\Application\Compliance\CreateAuditHandler;
use IPCA\SafetyCompliance\Domain\Compliance\AuditRepositoryInterface;
use IPCA\SafetyCompliance\Domain\Compliance\FindingActionRepositoryInterface;
use IPCA\SafetyCompliance\Domain\Compliance\FindingRepositoryInterface;
use IPCA\SafetyCompliance\Infrastructure\Persistence\Compliance\PdoFindingRcaRepository;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final class AuditController
{
    public function __construct(
        private CreateAuditHandler $createAuditHandler,
        private AuditRepositoryInterface $auditRepo,
        private FindingRepositoryInterface $findingRepo,
        private FindingActionRepositoryInterface $actionRepo,
        private PdoFindingRcaRepository $findingRcaRepo
    ) {}

    public function create(Request $request, Response $response): Response
    {
        $data = (array)$request->getParsedBody();

        // TEMP DEV USER (must exist in users table)
        $defaultCreatedBy = '415712F4-C7D8-11F0-AD9A-84068FBD07E7';
        $createdBy = $data['created_by'] ?? $defaultCreatedBy;

        // NEW canonical fields
        $auditCategory = $data['audit_category'] ?? 'CAA';   // INTERNAL | CAA
        $auditEntity   = $data['audit_entity']   ?? 'UNKNOWN'; // BCAA / FAA / EPC / SPC / any CAA

        $audit = $this->createAuditHandler->handle(
            title:         $data['title'] ?? 'Untitled audit',
            auditCategory: $auditCategory,
            auditEntity:   $auditEntity,
            auditType:     $data['audit_type'] ?? 'CMS',
            externalRef:   $data['external_ref'] ?? null,
            subject:       $data['subject'] ?? null,
            createdBy:     $createdBy,
            startDate:     $data['start_date'] ?? null,
            endDate:       $data['end_date'] ?? null
        );

        $response->getBody()->write(json_encode($audit->toArray()));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(201);
    }

    public function list(Request $request, Response $response): Response
    {
        $audits = $this->auditRepo->findAll();

        $out = [];
        foreach ($audits as $a) {
            $arr = $a->toArray();

            // Compute findings counts per audit
            $findings = $this->findingRepo->findByAudit($arr['id']);
            $total = is_array($findings) ? count($findings) : 0;

            $open = 0;
            if (is_array($findings)) {
                foreach ($findings as $f) {
                    $fa = $f->toArray();
                    if (($fa['status'] ?? '') !== 'CLOSED') $open++;
                }
            }

            $arr['findings_total'] = $total;
            $arr['findings_open']  = $open;

            $out[] = $arr;
        }

        $response->getBody()->write(json_encode($out));
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

    /**
     * GET /compliance/audits/{id}/report
     */
    public function exportReport(Request $request, Response $response, array $args): Response
{
    $auditId = $args['id'];

    $audit = $this->auditRepo->findById($auditId);
    if (!$audit) {
        $response->getBody()->write('Audit not found');
        return $response->withStatus(404);
    }

    $findings = $this->findingRepo->findByAudit($auditId);
    $auditArr = $audit->toArray();

    $findingBlocks = [];
    $i = 1;
    foreach ($findings as $f) {
        $fa = $f->toArray();

        $rcaSteps = $this->findingRcaRepo->findSteps($fa['id']) ?? [];
        $actions  = $this->actionRepo->findByFindingId($fa['id']);

        $findingBlocks[] = [
            'idx' => str_pad((string)$i, 2, '0', STR_PAD_LEFT),
            'finding' => $fa,
            'rcaSteps' => $rcaSteps,
            'actions' => array_map(fn($a) => $a->toArray(), $actions),
        ];
        $i++;
    }

    // Template path
    $templatePath = __DIR__ . '/../../Templates/bcaa_rca_cap.php';
    if (!file_exists($templatePath)) {
        $response->getBody()->write('Template not found: ' . $templatePath);
        return $response->withStatus(500);
    }

    $data = [
        'audit' => $auditArr,
        'findingBlocks' => $findingBlocks,
        'revision' => '1',
        'reportDate' => date('d/m/Y'),
        // if you later store audit date separately, use that field; for now start_date/end_date
        'auditDateLabel' => trim(
            ($auditArr['start_date'] ?? '') .
            (($auditArr['end_date'] ?? '') ? ' – ' . $auditArr['end_date'] : '')
        ),
        'organizationName' => 'EuroPilot Center', // change later if needed
    ];

    ob_start();
    include $templatePath;
    $html = ob_get_clean();

    $dompdf = new \Dompdf\Dompdf([
    'isRemoteEnabled' => true,
    'isHtml5ParserEnabled' => true,
    'isPhpEnabled' => true,
	]);
   
	$options = $dompdf->getOptions();
	$options->set('isRemoteEnabled', true);
	$options->set('isHtml5ParserEnabled', true);
	// ✅ allow local filesystem images
	$options->set('isChrootEnabled', false); // Dompdf v2+
	$options->setChroot(realpath(__DIR__ . '/../../../../')); // allow /backend-php and /public under project
	$dompdf->setOptions($options);	
		

    $dompdf->loadHtml($html, 'UTF-8');

    // ✅ Landscape A4 as requested
    $dompdf->setPaper('A4', 'landscape');

    $dompdf->render();

    // Footer: left copyright, right page number
    $canvas = $dompdf->getCanvas();
    $font = $dompdf->getFontMetrics()->getFont("Helvetica", "normal");
    $canvas->page_text(36, 565, "Copyright - IPCA.aero", $font, 9, [0,0,0]);
    $canvas->page_text(760, 565, "Page {PAGE_NUM}", $font, 9, [0,0,0]);

    $pdf = $dompdf->output();
    $filename = ($auditArr['external_ref'] ?? 'audit') . '_RCA_CAP.pdf';

    $response->getBody()->write($pdf);
    return $response
        ->withHeader('Content-Type', 'application/pdf')
        ->withHeader('Content-Disposition', 'inline; filename="' . $filename . '"');
}
	
	
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $args['id'];
        $data = (array)$request->getParsedBody();

        $fields = [
            'external_ref'    => $data['external_ref'] ?? null,
            'title'           => $data['title'] ?? null,
            'audit_category'  => $data['audit_category'] ?? null, // NEW
            'audit_entity'    => $data['audit_entity'] ?? null,   // NEW
            'audit_type'      => $data['audit_type'] ?? null,
            'status'          => $data['status'] ?? null,
            'subject'         => array_key_exists('subject', $data) ? $data['subject'] : null,
            'start_date'      => array_key_exists('start_date', $data) ? $data['start_date'] : null,
            'end_date'        => array_key_exists('end_date', $data) ? $data['end_date'] : null,
            'closed_date'     => array_key_exists('closed_date', $data) ? $data['closed_date'] : null,
        ];

        $this->auditRepo->updateById($id, $fields);

        $audit = $this->auditRepo->findById($id);
        $response->getBody()->write(json_encode($audit ? $audit->toArray() : ['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    }
}