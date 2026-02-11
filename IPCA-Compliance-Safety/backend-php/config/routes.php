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

// TEMP: env debug (remove after fixing)
$app->get('/_env', function ($request, $response) {
    $keys = [
        'DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','DB_SSLMODE',
        'DB_NAME','DB_USER','DB_PASS'
    ];

    $out = [];
    foreach ($keys as $k) {
        $v = $_ENV[$k] ?? getenv($k) ?? null;
        if ($v === null) {
            $out[$k] = null;
        } else {
            // don't leak secrets
            $out[$k] = ($k === 'DB_PASSWORD') ? '***set***' : $v;
        }
    }

    $response->getBody()->write(json_encode($out));
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
