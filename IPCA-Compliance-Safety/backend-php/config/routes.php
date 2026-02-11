<?php
declare(strict_types=1);

use Slim\App;
use Slim\Routing\RouteCollectorProxy;
use IPCA\SafetyCompliance\Infrastructure\Http\Controllers\AuditController;
use IPCA\SafetyCompliance\Infrastructure\Http\Controllers\FindingController;

return function (App $app): void {

    // Health
    $app->get('/health', function ($request, $response) {
        $response->getBody()->write(json_encode(['status' => 'ok']));
        return $response->withHeader('Content-Type', 'application/json');
    });

	
    // Compliance
    $app->group('/compliance', function (RouteCollectorProxy $group): void {

        // --------------------
        // AUDITS
        // --------------------
        $group->get('/audits',  [AuditController::class, 'list']);
        $group->post('/audits', [AuditController::class, 'create']);
        $group->get('/audits/{id}', [AuditController::class, 'detail']);
		$group->patch('/audits/{id}', [AuditController::class, 'update']);

        // Export authority-ready PDF
        $group->get('/audits/{id}/report', [AuditController::class, 'exportReport']);

        // --------------------
        // FINDINGS
        // --------------------
        // Dashboard list (open findings)
        $group->get('/findings', [FindingController::class, 'listAll']);

        // Findings inside an audit
        $group->get('/audits/{id}/findings',  [FindingController::class, 'listForAudit']);
        $group->post('/audits/{id}/findings', [FindingController::class, 'createForAudit']);

        // Edit a finding
        $group->patch('/findings/{id}', [FindingController::class, 'update']);

        // --------------------
        // ACTIONS (CAP)
        // --------------------
        // List + create actions for a finding
        $group->get('/findings/{id}/actions',  [FindingController::class, 'listActions']);
        $group->post('/findings/{id}/actions', [FindingController::class, 'addAction']);

        // Edit a single CAP action by its numeric ID (NEW)
        $group->patch('/actions/{id}', [FindingController::class, 'updateAction']);

        // AI CAP options
        $group->post('/findings/{id}/actions/suggest-ai', [FindingController::class, 'suggestCap']);

        // --------------------
        // RCA (AI 5-Whys chain)
        // --------------------
        // Load saved RCA
        $group->get('/findings/{id}/rca', [FindingController::class, 'getRca']);

        // Generate next Why step
        $group->post('/findings/{id}/rca/next-step', [FindingController::class, 'rcaNextStep']);

        // Save RCA chain
        $group->post('/findings/{id}/rca', [FindingController::class, 'saveRca']);

        // --------------------
        // MCCF / Manual / Notes
        // --------------------
        $group->get('/mccf', [FindingController::class, 'listMccfItems']);
        $group->post('/findings/{id}/manual-ref', [FindingController::class, 'saveManualRef']);
        $group->post('/findings/{id}/mccf-link', [FindingController::class, 'saveMccfLink']);
        $group->post('/findings/{id}/notes', [FindingController::class, 'saveNotes']);
    });
};
