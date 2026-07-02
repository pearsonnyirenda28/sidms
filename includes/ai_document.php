<?php
/**
 * AI Document Assistant – Multi‑provider (OpenAI, Gemini, Cohere) + local fallback + secure web search
 * Knowledge base, document retrieval, and DuckDuckGo integration.
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/encryption.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/office_preview.php';
require_once __DIR__ . '/web_search.php';

if (!defined('AI_CACHE_MINUTES')) define('AI_CACHE_MINUTES', 60);
if (!defined('OPENAI_MODEL')) define('OPENAI_MODEL', 'gpt-4o-mini');
if (!defined('GEMINI_MODEL')) define('GEMINI_MODEL', 'gemini-2.0-flash');

// -----------------------------------------------------------------------------
// Text Extraction (PDF, Office, plain text)
// -----------------------------------------------------------------------------
function extractTextFromFile(string $filePath, string $mimeType): string {
    if ($mimeType === 'application/pdf') {
        return extractPdfText($filePath);
    }
    if (in_array($mimeType, [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-powerpoint'
    ])) {
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            $text = extractPlainTextFromZip($zip, $mimeType);
            $zip->close();
            return $text ?: '';
        }
        return '';
    }
    $content = @file_get_contents($filePath);
    return ($content !== false) ? $content : '';
}

// -----------------------------------------------------------------------------
// PDF text extraction (already present, unchanged)
// -----------------------------------------------------------------------------
function extractPdfText(string $filePath): string {
    $data = file_get_contents($filePath);
    if (!$data) return '';
    $text = '';
    $decompress = function ($streamData) {
        $decoded = @gzuncompress($streamData);
        if ($decoded !== false) return $decoded;
        return $streamData;
    };
    if (preg_match_all('/\d+\s+\d+\s+obj\s*(.*?)endobj/s', $data, $objects)) {
        foreach ($objects[1] as $objContent) {
            if (preg_match('/stream\s+(.*?)endstream/s', $objContent, $streamMatch)) {
                $decoded = $decompress($streamMatch[1]);
                $text .= extractBlock($decoded);
            } else {
                $text .= extractBlock($objContent);
            }
        }
    }
    if (empty($text)) {
        $text .= extractBlock($data);
    }
    return trim(preg_replace('/\s+/', ' ', $text));
}

function extractBlock(string $content): string {
    $text = '';
    if (preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $blocks)) {
        foreach ($blocks[1] as $block) {
            if (preg_match_all('/\(([^)]*)\)\s*Tj/', $block, $tjMatches)) {
                foreach ($tjMatches[1] as $t) $text .= pdfDecodeText($t) . ' ';
            }
            if (preg_match_all('/\[(.*?)\]\s*TJ/', $block, $tjArrayMatches)) {
                foreach ($tjArrayMatches[1] as $tjArray) {
                    if (preg_match_all('/\(([^)]*)\)/', $tjArray, $inner)) {
                        foreach ($inner[1] as $innerText) $text .= pdfDecodeText($innerText);
                    }
                }
            }
        }
    }
    return $text;
}

function pdfDecodeText(string $str): string {
    return preg_replace_callback('/\\\\([nrt\\\\()]|(\d{1,3}))/i', function ($m) {
        if (isset($m[2])) return chr(octdec($m[2]));
        switch ($m[1]) {
            case 'n': return "\n";
            case 'r': return "\r";
            case 't': return "\t";
            case '\\':
            case '(':
            case ')':
                return $m[1];
            default: return $m[0];
        }
    }, $str);
}

// -----------------------------------------------------------------------------
// External AI Providers
// -----------------------------------------------------------------------------
function callAI(string $prompt, string $system = 'You are a helpful assistant.'): string {
    $providers = [];
    if (!empty(OPENAI_API_KEY)) $providers[] = 'openai';
    if (!empty(GEMINI_API_KEY)) $providers[] = 'gemini';
    if (!empty(COHERE_API_KEY)) $providers[] = 'cohere';
    foreach ($providers as $provider) {
        $res = false;
        switch ($provider) {
            case 'openai': $res = callOpenAI($prompt, $system); break;
            case 'gemini': $res = callGemini($prompt, $system); break;
            case 'cohere': $res = callCohere($prompt, $system); break;
        }
        if ($res !== false && $res !== '') return $res;
    }
    return advancedLocalAI($prompt, $system);
}

// ... (callOpenAI, callGemini, callCohere, callOpenAIChat, callGeminiChat, callCohereChat, conversationalAI, advancedLocalAI, etc.) remain exactly as previously corrected
// They are omitted here for brevity but should be kept in your file unchanged.

// -----------------------------------------------------------------------------
// Knowledge Base, Q&A, etc.
// -----------------------------------------------------------------------------
function searchKnowledgeBase(string $question): array {
    $qWords = array_unique(array_map('stemWord', explode(' ', strtolower($question))));
    $stmt = db()->query('SELECT term, definition, aliases, category FROM ai_knowledge');
    $results = [];
    while ($row = $stmt->fetch()) {
        $score = 0;
        $termWords = array_map('stemWord', explode(' ', strtolower($row['term'])));
        $aliasWords = array_map('stemWord', explode(' ', strtolower($row['aliases'] ?? '')));
        foreach ($qWords as $qw) {
            if (strlen($qw) < 3) continue;
            if (in_array($qw, $termWords)) $score += 3;
            if (in_array($qw, $aliasWords)) $score += 2;
            if (stripos($row['definition'], $qw) !== false) $score += 1;
        }
        if ($score > 0) $results[] = ['entry' => $row, 'score' => $score];
    }
    usort($results, fn($a, $b) => $b['score'] - $a['score']);
    return $results;
}

function stemWord(string $word): string {
    if (strlen($word) < 4) return $word;
    $word = preg_replace('/(s|es|ies)$/', '', $word);
    $word = preg_replace('/(ed|ing)$/', '', $word);
    $word = preg_replace('/(ly|ment|ness|ship|tion|sion)$/', '', $word);
    return $word;
}

// ... (intelligentAnswer, isWebSearchIntent, searchUserDocuments, getDocumentInsights, askDocumentQuestion, generalQA, findSimilarCachedQA) remain unchanged.
// They are already correct and rely on callAI and local functions.