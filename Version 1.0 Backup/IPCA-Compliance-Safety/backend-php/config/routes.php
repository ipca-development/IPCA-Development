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

        // Optional export endpoint (works once implemented)
        $group->get('/audits/{id}/report', [AuditController::class, 'exportReport']);

        // --------------------
        // FINDINGS
        // --------------------
        // Dashboard list
        $group->get('/findings', [FindingController::class, 'listAll']);

        // Findings inside an audit
        $group->get('/audits/{id}/findings',  [FindingController::class, 'listForAudit']);
        $group->post('/audits/{id}/findings', [FindingController::class, 'createForAudit']);

        // Edit a finding
        $group->patch('/findings/{id}', [FindingController::class, 'update']);

        // --------------------
        // ACTIONS (CAP)
        // --------------------
        $group->get('/findings/{id}/actions',  [FindingController::class, 'listActions']);
        $group->post('/findings/{id}/actions', [FindingController::class, 'addAction']);

        // AI CAP options (Step D, can be stub for now)
        $group->post('/findings/{id}/actions/suggest-ai', [FindingController::class, 'suggestCap']);
		$group->get('/findings/{id}/actions/suggest-ai', [FindingController::class, 'suggestCap']); // TEMP DEBUG

        // --------------------
		// RCA (AI 5-Whys chain)
		// --------------------
		$group->get('/findings/{id}/rca', [FindingController::class, 'getRca']);  
		$group->post('/findings/{id}/rca/next-step', [FindingController::class, 'rcaNextStep']);
		$group->post('/findings/{id}/rca', [FindingController::class, 'saveRca']);

        // --------------------
        // MCCF / Manual / Notes (can be stub)
        // --------------------
        $group->get('/mccf', [FindingController::class, 'listMccfItems']);
        $group->post('/findings/{id}/manual-ref', [FindingController::class, 'saveManualRef']);
        $group->post('/findings/{id}/mccf-link', [FindingController::class, 'saveMccfLink']);
        $group->post('/findings/{id}/notes', [FindingController::class, 'saveNotes']);
    });
};