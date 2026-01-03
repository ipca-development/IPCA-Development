<?php
declare(strict_types=1);

namespace IPCA\SafetyCompliance\Infrastructure\Ai;

final class OpenAiClient
{
    private string $apiKey;
    private string $model;

    public function __construct(string $apiKey, string $model = 'gpt-5')
    {
        $this->apiKey = $apiKey;
        $this->model  = $model;
    }

    /**
     * Calls OpenAI Responses API and returns plain text output.
     */
    
	public function responseText(string $input, ?string $modelOverride = null): string
{
    $url = 'https://api.openai.com/v1/responses';

    $payload = json_encode([
        'model' => $modelOverride ?: $this->model,
        'input' => $input,
    ], JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        throw new \RuntimeException('Failed to JSON-encode OpenAI payload.');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey,
        ],
        CURLOPT_POSTFIELDS     => $payload,

        // IMPORTANT for MAMP FastCGI 30s idle timeout:
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT        => 25,

        CURLOPT_FAILONERROR    => false,
    ]);

    $raw  = curl_exec($ch);
    $err  = curl_error($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($raw === false) {
        throw new \RuntimeException('OpenAI request failed: ' . $err);
    }
    if ($code < 200 || $code >= 300) {
        throw new \RuntimeException('OpenAI HTTP ' . $code . ': ' . $raw);
    }

    $json = json_decode($raw, true);
    if (!is_array($json)) {
        throw new \RuntimeException('OpenAI returned invalid JSON. Raw: ' . $raw);
    }

    $text = '';
    if (isset($json['output']) && is_array($json['output'])) {
        foreach ($json['output'] as $out) {
            if (!isset($out['content']) || !is_array($out['content'])) continue;
            foreach ($out['content'] as $c) {
                if (($c['type'] ?? '') === 'output_text') {
                    $text .= (string)($c['text'] ?? '');
                }
            }
        }
    }

    $text = trim($text);
    if ($text === '') {
        $text = trim($raw);
    }

    return $text;
}	
		
		
}