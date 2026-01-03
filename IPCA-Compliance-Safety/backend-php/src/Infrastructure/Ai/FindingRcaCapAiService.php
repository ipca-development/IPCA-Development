<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Ai;

use PDO;

final class FindingRcaCapAiService
{
    public function __construct(
        private PDO $pdo,
        private OpenAiClient $client,
        private string $model = 'gpt-5'
    ) {}

    /**
     * Runs AI RCA+CAP for one finding (BINARY(16) finding_id).
     * - Logs run in ai_finding_runs
     * - Upserts finding_rca.steps_json
     * - Inserts finding_actions
     *
     * $createdBy can be null (recommended at first).
     */
    public function runRcaAndCap(string $findingIdBin16, ?string $createdByBin16 = null): array
    {
        $ctx = $this->loadFindingContext($findingIdBin16);
        if (!$ctx) {
            throw new \RuntimeException('Finding not found.');
        }
        if (empty($ctx['requirement_key'])) {
            throw new \RuntimeException('Finding has no requirement_key. Set findings.requirement_key first.');
        }

        $evidenceSnapshot = $this->buildEvidenceSnapshotJson($ctx['evidence']);
        $prompt = $this->buildPrompt($ctx, $evidenceSnapshot);

        // Call OpenAI (your existing client already hits /v1/responses)
        $rawText = $this->client->responseText($prompt, $this->model);

        // Always log run (even if parsing fails)
        $runId = $this->insertAiRun($findingIdBin16, 'RCA_AND_CAP', $this->model, $prompt, $evidenceSnapshot, $rawText, $createdByBin16);

        // Parse strict JSON
        $parsed = json_decode($rawText, true);
        if (!is_array($parsed)) {
            throw new \RuntimeException('AI did not return valid JSON. Run logged under ai_finding_runs.id=' . $runId);
        }

        // Persist in one transaction
        $this->pdo->beginTransaction();
        try {
            // Upsert RCA JSON into finding_rca.steps_json
            $rcaJson = json_encode($parsed['rca'] ?? $parsed, JSON_UNESCAPED_UNICODE);
            $this->upsertFindingRca($findingIdBin16, $rcaJson, $createdByBin16);

            // Insert CAP actions
            if (!empty($parsed['cap']) && is_array($parsed['cap'])) {
                $this->insertFindingActions($findingIdBin16, $parsed['cap']);
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $parsed['_meta'] = [
            'ai_run_id' => $runId
        ];
        return $parsed;
    }

    // ---------------- DB: Context ----------------

    private function loadFindingContext(string $findingIdBin16): ?array
    {
        // Finding + MCCF requirement
        $sql = "
            SELECT
                f.id AS finding_id,
                f.reference,
                f.title,
                f.classification,
                f.status,
                f.severity,
                f.description,
                f.regulation_ref,
                f.requirement_key,

                r.manual_code AS req_manual_code,
                r.regulation_ref AS req_regulation_ref,
                r.manual_part AS req_manual_part,
                r.item_no AS req_item_no,
                r.sub_item_no AS req_sub_item_no,
                r.manual_section_ref AS req_manual_section_ref,
                r.subject AS req_subject,
                r.requirement_text AS req_requirement_text
            FROM findings f
            LEFT JOIN mccf_requirements r
              ON r.requirement_key = f.requirement_key
            WHERE f.id = :finding_id
            LIMIT 1
        ";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':finding_id', $findingIdBin16, PDO::PARAM_LOB);
        $st->execute();
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) return null;

        // Evidence excerpts linked to requirement_key
        $sqlEv = "
            SELECT
                l.excerpt_id,
                l.link_type,
                l.confidence,
                e.manual_code,
                e.manual_part,
                e.section_ref,
                e.title,
                e.text
            FROM mccf_excerpt_links l
            JOIN manual_excerpts e
              ON e.excerpt_id = l.excerpt_id
            WHERE l.requirement_key = :rk
            ORDER BY
              (l.link_type='PRIMARY') DESC,
              e.manual_code, e.manual_part, e.section_ref
        ";
        $st2 = $this->pdo->prepare($sqlEv);
        $st2->execute([':rk' => $row['requirement_key']]);
        $row['evidence'] = $st2->fetchAll(PDO::FETCH_ASSOC);

        return $row;
    }

    private function buildEvidenceSnapshotJson(array $evidenceRows): string
    {
        // deterministic cap to keep prompts stable
        $max = 10;
        $out = [];
        $i = 0;

        foreach ($evidenceRows as $ex) {
            $i++;
            if ($i > $max) break;

            $out[] = [
                'excerpt_id'  => (string)$ex['excerpt_id'],
                'link_type'   => (string)$ex['link_type'],
                'confidence'  => (string)$ex['confidence'],
                'manual_code' => (string)$ex['manual_code'],
                'manual_part' => $ex['manual_part'],
                'section_ref' => (string)$ex['section_ref'],
                'title'       => (string)$ex['title'],
                'text'        => (string)$ex['text'],
            ];
        }

        return json_encode($out, JSON_UNESCAPED_UNICODE);
    }

    private function buildPrompt(array $ctx, string $evidenceSnapshotJson): string
    {
        // keep it strict to avoid “creative” outputs
        $p = [];
        $p[] = "You are the Compliance RCA/CAP assistant for an aviation compliance system.";
        $p[] = "Return ONLY valid JSON. No markdown. No commentary.";
        $p[] = "Do NOT invent regulations, manual text, or evidence.";
        $p[] = "Use ONLY the evidence excerpts provided.";
        $p[] = "If evidence is insufficient, ask targeted questions in missing_info_questions.";
        $p[] = "";
        $p[] = "FINDING:";
        $p[] = "reference: " . ($ctx['reference'] ?? '');
        $p[] = "title: " . ($ctx['title'] ?? '');
        $p[] = "classification: " . ($ctx['classification'] ?? '');
        $p[] = "severity: " . ($ctx['severity'] ?? '');
        $p[] = "status: " . ($ctx['status'] ?? '');
        $p[] = "description: " . ($ctx['description'] ?? '');
        $p[] = "";
        $p[] = "MCCF REQUIREMENT:";
        $p[] = "requirement_key: " . ($ctx['requirement_key'] ?? '');
        $p[] = "regulation_ref: " . ($ctx['req_regulation_ref'] ?? '');
        $p[] = "manual_section_ref: " . ($ctx['req_manual_section_ref'] ?? '');
        $p[] = "subject: " . ($ctx['req_subject'] ?? '');
        $p[] = "requirement_text: " . ($ctx['req_requirement_text'] ?? '');
        $p[] = "";
        $p[] = "EVIDENCE_EXCERPTS_JSON:";
        $p[] = $evidenceSnapshotJson;
        $p[] = "";
        $p[] = "OUTPUT JSON SCHEMA:";
        $p[] = "{";
        $p[] = "  \"rca\": {\"summary\": \"...\", \"steps\": [ {\"step\": 1, \"title\": \"...\", \"analysis\": \"...\", \"evidence_excerpt_ids\": [\"...\"]} ]},";
        $p[] = "  \"cap\": [ {\"action_type\": \"CORRECTIVE\"|\"PREVENTIVE\"|\"CONTAINMENT\", \"description\": \"...\", \"due_days\": 30, \"evidence_excerpt_ids\": [\"...\"]} ],";
        $p[] = "  \"missing_info_questions\": [\"...\"]";
        $p[] = "}";
        return implode("\n", $p);
    }

    // ---------------- DB: persistence ----------------

    private function insertAiRun(
        string $findingIdBin16,
        string $runType,
        string $model,
        string $prompt,
        string $evidenceSnapshot,
        string $responseJson,
        ?string $createdByBin16
    ): int {
        $sql = "
            INSERT INTO ai_finding_runs
              (finding_id, run_type, model, prompt, evidence_snapshot, response_json, created_by)
            VALUES
              (:finding_id, :run_type, :model, :prompt, :evidence, :response_json, :created_by)
        ";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':finding_id', $findingIdBin16, PDO::PARAM_LOB);
        $st->bindValue(':run_type', $runType);
        $st->bindValue(':model', $model);
        $st->bindValue(':prompt', $prompt);
        $st->bindValue(':evidence', $evidenceSnapshot);
        $st->bindValue(':response_json', $responseJson);
        if ($createdByBin16 === null) {
            $st->bindValue(':created_by', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':created_by', $createdByBin16, PDO::PARAM_LOB);
        }
        $st->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function upsertFindingRca(string $findingIdBin16, string $stepsJson, ?string $createdByBin16): void
    {
        $sql = "
            INSERT INTO finding_rca (finding_id, steps_json, created_by, created_at, updated_at)
            VALUES (:finding_id, :steps_json, :created_by, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
              steps_json = VALUES(steps_json),
              updated_at = NOW()
        ";
        $st = $this->pdo->prepare($sql);
        $st->bindValue(':finding_id', $findingIdBin16, PDO::PARAM_LOB);
        $st->bindValue(':steps_json', $stepsJson);
        if ($createdByBin16 === null) {
            $st->bindValue(':created_by', null, PDO::PARAM_NULL);
        } else {
            $st->bindValue(':created_by', $createdByBin16, PDO::PARAM_LOB);
        }
        $st->execute();
    }

    private function insertFindingActions(string $findingIdBin16, array $capActions): void
    {
        // Insert without specifying id (assumes AUTO_INCREMENT in modern schema)
        $sql = "
            INSERT INTO finding_actions
              (finding_id, action_type, description, responsible_id, due_date, completed_at, effectiveness, created_at)
            VALUES
              (:finding_id, :action_type, :description, :responsible_id, :due_date, NULL, 'NOT_EVALUATED', NOW())
        ";
        $st = $this->pdo->prepare($sql);

        foreach ($capActions as $a) {
            if (!is_array($a)) continue;

            $type = (string)($a['action_type'] ?? 'CORRECTIVE');
            $desc = (string)($a['description'] ?? '');
            $dueDays = (int)($a['due_days'] ?? 30);
            if ($dueDays < 0) $dueDays = 30;

            $dueDate = (new \DateTimeImmutable('now'))->modify('+' . $dueDays . ' days')->format('Y-m-d');

            $st->bindValue(':finding_id', $findingIdBin16, PDO::PARAM_LOB);
            $st->bindValue(':action_type', $type);
            $st->bindValue(':description', $desc);
            $st->bindValue(':responsible_id', null, PDO::PARAM_NULL);
            $st->bindValue(':due_date', $dueDate);
            $st->execute();
        }
    }
}