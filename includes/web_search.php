<?php
function secureWebSearch(string $query): string {
    $url = 'https://api.duckduckgo.com/?q=' . urlencode($query)
         . '&format=json&no_html=1&no_redirect=1&skip_disambig=1';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 12,
        CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_USERAGENT      => 'SIDMS/2.0',
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 2,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);
    if ($response === false || $httpCode !== 200) {
        error_log('DuckDuckGo API error: ' . ($error ?: "HTTP $httpCode"));
        return '';
    }
    $data = json_decode($response, true);
    if (!is_array($data)) return '';
    return extractRelevantSnippets($data);
}

function getAllowedDomains(): array {
    return [
        'en.wikipedia.org', 'simple.wikipedia.org',
        'scholar.google.com', 'arxiv.org',
        'docs.php.net', 'github.com',
        'stackoverflow.com', 'developer.mozilla.org',
        'w3.org',
    ];
}

function isAllowedDomain(string $url): bool {
    $host = parse_url($url, PHP_URL_HOST);
    if (!$host) return false;
    $host = strtolower($host);
    foreach (getAllowedDomains() as $allowed) {
        if ($host === $allowed || str_ends_with($host, '.' . $allowed)) return true;
    }
    return false;
}

function extractRelevantSnippets(array $data): string {
    $snippets = [];
    if (!empty($data['AbstractText']) && (!$data['AbstractURL'] || isAllowedDomain($data['AbstractURL']))) {
        $snippets[] = $data['AbstractText'];
    }
    if (!empty($data['Answer'])) {
        $snippets[] = $data['Answer'];
    }
    if (!empty($data['Definition'])) {
        $snippets[] = $data['Definition'];
    }
    if (!empty($data['RelatedTopics']) && is_array($data['RelatedTopics'])) {
        foreach ($data['RelatedTopics'] as $topic) {
            if (isset($topic['Topics']) && is_array($topic['Topics'])) {
                foreach ($topic['Topics'] as $sub) {
                    $txt = $sub['Text'] ?? '';
                    $url = $sub['FirstURL'] ?? '';
                    if ($txt && (!$url || isAllowedDomain($url))) $snippets[] = strip_tags($txt);
                }
            } else {
                $txt = $topic['Text'] ?? '';
                $url = $topic['FirstURL'] ?? '';
                if ($txt && (!$url || isAllowedDomain($url))) $snippets[] = strip_tags($txt);
            }
        }
    }
    return implode('. ', array_slice(array_unique($snippets), 0, 5));
}