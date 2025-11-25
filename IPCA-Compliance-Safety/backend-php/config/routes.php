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

    // Compliance group
    $app->group('/compliance', function (RouteCollectorProxy $group): void {

        // AUDITS
        $group->post('/audits', [AuditController::class, 'create']);
        $group->get('/audits',  [AuditController::class, 'list']);
        $group->get('/audits/{id}', [AuditController::class, 'detail']);

        // FINDINGS
        $group->post('/audits/{id}/findings', [FindingController::class, 'createForAudit']);
        $group->get('/audits/{id}/findings',  [FindingController::class, 'listForAudit']);
        $group->patch('/findings/{id}',       [FindingController::class, 'update']);

        // ACTIONS
        $group->post('/findings/{id}/actions',                       [FindingController::class, 'addAction']);
        $group->get('/findings/{id}/actions',                        [FindingController::class, 'listActions']);
        $group->patch('/findings/{id}/actions/{actionId}',           [FindingController::class, 'updateAction']);

        // RCA
        $group->post('/findings/{id}/rca', [FindingController::class, 'recordRca']);
    });
};