<?php
/*
 * AI caption writer for the Posts page. Supports Anthropic (Claude) and OpenAI.
 * The AI only drafts: staff always review and edit before a post is saved or sent to Facebook.
 */
declare(strict_types=1);

const AI_DEFAULT_MODELS = ['anthropic' => 'claude-haiku-5-5', 'openai' => 'gpt-6-luna'];
const AI_UNDER18 = 'Not for sale to persons under the age of 18. Drink responsibly.';

function ai_provider(): ?string {
    $p = setting('ai_provider');
    return in_array($p, ['anthropic', 'openai'], true) && setting('ai_key_' . $p) ? $p : null;
}

function ai_system_prompt(): string {
    return <<<TXT
You write social media posts for Gesond Liquor Restaurant, a licensed restaurant, bar and entertainment venue at Stand No. 92, Mapela Road, Danisane Village, Mapela, Limpopo, South Africa.
Brand: "One Love. One Vibe. One Soul." Music: Lekompo, Amapiano, Afro House. Flagship series: Saturday Linkup. Website: www.gesondlifestyle.co.za. WhatsApp: 082 643 9779. Gesond Rewards: free membership, earn points on spend.
Audience: adults 18+ in Mapela, Mokopane and surrounds, from young party-goers to grown-ups who enjoy food and a relaxed vibe.

Rules:
- Use ONLY facts given in the staff notes (names, dates, prices, times). Never invent DJs, prices, times, prizes or promises.
- Warm, energetic South African English. A few relevant emojis are fine. Keep it under 900 characters.
- Never encourage heavy or fast drinking, drinking games, drink-driving, or link alcohol to success or attractiveness. Never target under-18s or school learners as drinkers.
- End the body with this exact line: "Not for sale to persons under the age of 18. Drink responsibly."
- Add 3 to 6 hashtags such as #Gesond #Mapela.
- Reply with JSON only: {"title": "short headline, max 80 characters", "body": "the post text"}
TXT;
}

function ai_http(string $url, array $headers, array $payload): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => array_merge(['Content-Type: application/json'], $headers),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return [$code, json_decode((string)$raw, true) ?: [], $err];
}

/* Returns ['title' => ..., 'body' => ...] or throws RuntimeException with a staff-readable message. */
function ai_draft_post(string $notes, string $tone): array {
    $p = ai_provider();
    if (!$p) throw new RuntimeException('AI writing is not connected. An admin can connect it in Settings.');
    if (!function_exists('curl_init')) throw new RuntimeException('The server cannot reach the AI service.');
    $key = (string)setting('ai_key_' . $p);
    $model = setting('ai_model_' . $p) ?: AI_DEFAULT_MODELS[$p];
    $user = "Tone: $tone\nStaff notes (the only facts you may use):\n" . $notes;

    if ($p === 'anthropic') {
        [$code, $res, $err] = ai_http('https://api.anthropic.com/v1/messages',
            ['x-api-key: ' . $key, 'anthropic-version: 2023-06-01'],
            ['model' => $model, 'max_tokens' => 900, 'system' => ai_system_prompt(), 'messages' => [['role' => 'user', 'content' => $user]]]);
        $text = $res['content'][0]['text'] ?? '';
    } else {
        [$code, $res, $err] = ai_http('https://api.openai.com/v1/chat/completions',
            ['Authorization: Bearer ' . $key],
            ['model' => $model, 'messages' => [['role' => 'system', 'content' => ai_system_prompt()], ['role' => 'user', 'content' => $user]]]);
        $text = $res['choices'][0]['message']['content'] ?? '';
    }
    if ($code !== 200 || $text === '') {
        $msg = $res['error']['message'] ?? ($err ?: 'HTTP ' . $code);
        throw new RuntimeException('The AI service returned an error: ' . mb_substr((string)$msg, 0, 200));
    }
    if (preg_match('/\{.*\}/s', $text, $m)) $text = $m[0];
    $j = json_decode($text, true);
    if (!is_array($j) || empty($j['title']) || empty($j['body'])) throw new RuntimeException('The AI reply could not be read. Try again.');
    $title = mb_substr(trim((string)$j['title']), 0, 120);
    $body = trim((string)$j['body']);
    if (stripos($body, 'under the age of 18') === false) $body .= "\n\n" . AI_UNDER18;   // compliance line always present
    return ['title' => $title, 'body' => mb_substr($body, 0, 2000)];
}

function ai_test(string $provider): string {
    $saved = setting('ai_provider');
    try {
        set_setting('ai_provider', $provider);
        $d = ai_draft_post('Test only. Say hello to Gesond guests and invite them to visit www.gesondlifestyle.co.za.', 'friendly');
        return 'Working. Sample title: "' . $d['title'] . '"';
    } finally {
        set_setting('ai_provider', $saved);
    }
}
