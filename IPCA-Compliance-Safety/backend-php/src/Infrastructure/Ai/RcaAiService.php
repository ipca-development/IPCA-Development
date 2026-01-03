<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Ai;

final class RcaAiService
{
    public function __construct(
        private OpenAiClient $client
    ) {}

    /**
     * Generate the NEXT "Why" step only.
     *
     * @param array $finding associative array: reference,title,description,classification,severity,regulation_ref,target_date,manual_refs(optional)
     * @param array $steps   array of prior steps: [{whyNumber, question, answer}, ...] (answers may have been edited)
     * @return array         {whyNumber:int, question:string, answer:string}
     */
    public function generateNextStep(array $finding, array $steps): array
    {
        $nextWhy = count($steps) + 1;

        if ($nextWhy > 5) {
            return [
                'whyNumber' => 5,
                'question'  => 'RCA complete',
                'answer'    => 'RCA complete',
            ];
        }

        $findingText = $this->buildFindingContext($finding);

        $prev = '';
        foreach ($steps as $s) {
            $n = (int)($s['whyNumber'] ?? 0);
            if ($n < 1 || $n > 5) continue;

            $q = trim((string)($s['question'] ?? ''));
            $a = trim((string)($s['answer'] ?? ''));
            $prev .= "WHY {$n} QUESTION: {$q}\nWHY {$n} ANSWER (final): {$a}\n\n";
        }

        $prompt = <<<TXT
You are an aviation compliance expert writing a regulatory-grade Root Cause Analysis using the 5 Whys method.
You must produce the NEXT why step only (Why {$nextWhy}).

Rules:
- Do NOT blame individuals; focus on system/process/training/documentation/oversight.
- Build logically on the previous final answers (these may have been edited by the Compliance Manager).
- Use concise professional language suitable for an authority (CAA/BCAA/FAA).
- Output MUST be valid JSON ONLY with keys: whyNumber, question, answer.
- question should start with "Why ...?"
- answer should start with "Because ..."

FINDING CONTEXT:
{$findingText}

PREVIOUS WHY STEPS (final approved answers):
{$prev}

Now produce Why {$nextWhy}.
TXT;

        // RCA next-step is already fast; no override needed.
        $raw = $this->client->responseText($prompt);

        $decoded = $this->decodeJsonSafely($raw);
        if (!is_array($decoded)) {
            // fallback: wrap raw into a simple structure
            return [
                'whyNumber' => $nextWhy,
                'question'  => "Why (auto) – please refine",
                'answer'    => trim($raw),
            ];
        }

        // Force whyNumber to be correct
        $decoded['whyNumber'] = $nextWhy;

        // Ensure keys exist
        $decoded['question'] = (string)($decoded['question'] ?? '');
        $decoded['answer']   = (string)($decoded['answer'] ?? '');

        return $decoded;
    }

    /**
     * General JSON helper (used by CAP generator etc.)
     * The prompt MUST instruct the model to return JSON only.
     *
     * @param string $prompt
     * @param string|null $modelOverride e.g. 'gpt-4o-mini' for faster CAP generation
     */
    public function generateJson(string $prompt, ?string $modelOverride = null): array
    {
        $raw = $this->client->responseText($prompt, $modelOverride);

        $decoded = $this->decodeJsonSafely($raw);
        if (!is_array($decoded)) {
            throw new \RuntimeException("AI did not return valid JSON. Raw: " . $raw);
        }

        return $decoded;
    }

    private function buildFindingContext(array $f): string
    {
        $parts = [];

        $parts[] = "Reference: " . ($f['reference'] ?? '');
        $parts[] = "Title: " . ($f['title'] ?? '');
        $parts[] = "Classification: " . ($f['classification'] ?? '');
        $parts[] = "Severity: " . ($f['severity'] ?? '');
        $parts[] = "Regulation ref: " . ($f['regulation_ref'] ?? '');
        $parts[] = "Description:\n" . ($f['description'] ?? '');

        // Optional manual context: pass whatever you store/link later
        if (!empty($f['manual_refs']) && is_array($f['manual_refs'])) {
            $parts[] = "Linked manual references:\n" . implode("\n", $f['manual_refs']);
        }

        return implode("\n", $parts);
    }

    /**
     * Decode JSON from AI output robustly.
     * Handles:
     * - ```json ... ``` code fences
     * - leading text before JSON
     * - trailing text after JSON
     */
    private function decodeJsonSafely(string $raw): mixed
    {
        $text = trim($raw);

        // Remove Markdown code fences if present
        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(json)?/i', '', $text);
            $text = preg_replace('/```$/', '', $text);
            $text = trim($text);
        }

        // 1) Attempt direct decode
        $decoded = json_decode($text, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // 2) Attempt to extract first {...} JSON object from the response
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $maybe = substr($text, $start, $end - $start + 1);
            $decoded2 = json_decode($maybe, true);
            if (is_array($decoded2)) {
                return $decoded2;
            }
        }

        return null;
    }
}