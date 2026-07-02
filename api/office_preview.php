<?php
/**
 * Office Document Preview Converter – Pure PHP, no extensions required
 * Uses a built‑in ZIP reader and regex to parse .docx/.xlsx/.pptx
 *
 * Provides:
 *   - officeFileToHtml()
 *   - extractPlainTextFromZip()
 */

// ---------------------------------------------------------------------------
// Built‑in ZIP reader (drop‑in replacement for ZipArchive)
// ---------------------------------------------------------------------------
if (!class_exists('ZipArchive')) {
    class ZipArchive {
        public $numFiles = 0;
        private $entries = [];
        private $fp = null;

        public function open(string $filename, int $flags = 0): bool {
            if (!file_exists($filename)) return false;
            $this->fp = fopen($filename, 'rb');
            if (!$this->fp) return false;

            // Locate end-of-central-directory (EOCD)
            fseek($this->fp, -22, SEEK_END);
            $eocd = fread($this->fp, 22);
            if (substr($eocd, 0, 4) !== "\x50\x4b\x05\x06") {
                $commentLen = unpack('v', substr($eocd, 20, 2))[1];
                fseek($this->fp, -22 - $commentLen, SEEK_END);
                $eocd = fread($this->fp, 22 + $commentLen);
            }
            if (substr($eocd, 0, 4) !== "\x50\x4b\x05\x06") return false;

            $cdOffset = unpack('V', substr($eocd, 16, 4))[1];
            $cdSize   = unpack('V', substr($eocd, 12, 4))[1];

            // Parse central directory
            fseek($this->fp, $cdOffset);
            $cdData = fread($this->fp, $cdSize);
            $pos = 0;
            $this->entries = [];
            while ($pos < $cdSize) {
                if (substr($cdData, $pos, 4) !== "\x50\x4b\x01\x02") break;
                $nameLen    = unpack('v', substr($cdData, $pos + 28, 2))[1];
                $extraLen   = unpack('v', substr($cdData, $pos + 30, 2))[1];
                $commentLen = unpack('v', substr($cdData, $pos + 32, 2))[1];
                $compMethod = unpack('v', substr($cdData, $pos + 10, 2))[1];
                $compSize   = unpack('V', substr($cdData, $pos + 20, 4))[1];
                $localOff   = unpack('V', substr($cdData, $pos + 42, 4))[1];
                $name       = substr($cdData, $pos + 46, $nameLen);
                $this->entries[] = [
                    'name'        => $name,
                    'compMethod'  => $compMethod,
                    'compSize'    => $compSize,
                    'localOffset' => $localOff,
                ];
                $pos += 46 + $nameLen + $extraLen + $commentLen;
            }
            $this->numFiles = count($this->entries);
            return true;
        }

        public function getFromName(string $name): string|false {
            foreach ($this->entries as $entry) {
                if ($entry['name'] === $name) return $this->getEntryContent($entry);
            }
            return false;
        }

        public function getFromIndex(int $index): string|false {
            if (!isset($this->entries[$index])) return false;
            return $this->getEntryContent($this->entries[$index]);
        }

        private function getEntryContent(array $entry): string|false {
            fseek($this->fp, $entry['localOffset']);
            $localHeader = fread($this->fp, 30);
            $nameLen  = unpack('v', substr($localHeader, 26, 2))[1];
            $extraLen = unpack('v', substr($localHeader, 28, 2))[1];
            $dataStart = $entry['localOffset'] + 30 + $nameLen + $extraLen;
            fseek($this->fp, $dataStart);
            $compressed = fread($this->fp, $entry['compSize']);
            switch ($entry['compMethod']) {
                case 0: return $compressed;
                case 8: return @gzinflate($compressed);
                default: return false;
            }
        }

        public function getNameIndex(int $index): string|false {
            return $this->entries[$index]['name'] ?? false;
        }

        public function close(): void {
            if ($this->fp) fclose($this->fp);
        }
    }
}

// ---------------------------------------------------------------------------
// Image helper for previews
// ---------------------------------------------------------------------------
function getImageMime(string $imgData): string {
    $head = substr($imgData, 0, 8);
    if (strpos($head, "\x89PNG") === 0) return 'image/png';
    if (strpos($head, "\xFF\xD8\xFF") === 0) return 'image/jpeg';
    if (strpos($head, "GIF89a") === 0 || strpos($head, "GIF87a") === 0) return 'image/gif';
    return 'image/png';
}

function extractImagesArray(ZipArchive $zip, string $mediaFolder): array {
    $images = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (strpos($name, $mediaFolder) === 0 && $name !== $mediaFolder) {
            $data = $zip->getFromIndex($i);
            if ($data !== false && strlen($data) > 100) {
                $mime = getImageMime($data);
                $images[basename($name)] = 'data:' . $mime . ';base64,' . base64_encode($data);
            }
        }
    }
    return $images;
}

// ---------------------------------------------------------------------------
// Main entry: converts an Office file path to HTML preview
// ---------------------------------------------------------------------------
function officeFileToHtml(string $filePath, string $mimeType): string|false {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) return false;

    if (in_array($mimeType, [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword'
    ])) {
        $html = docxToHtml($zip);
    } elseif (in_array($mimeType, [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel'
    ])) {
        $html = xlsxToHtml($zip);
    } elseif (in_array($mimeType, [
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-powerpoint'
    ])) {
        $html = pptxToHtml($zip);
    } else {
        $html = false;
    }
    $zip->close();
    return $html;
}

// ---------------------------------------------------------------------------
// .docx → HTML  (regex‑based, no namespace worries)
// ---------------------------------------------------------------------------
function docxToHtml(ZipArchive $zip): string {
    $docXml = $zip->getFromName('word/document.xml');
    if (!$docXml) return '<p>Invalid document.</p>';

    $images = extractImagesArray($zip, 'word/media/');
    $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
    $imgMap = [];
    if ($relsXml) {
        if (preg_match_all('/<Relationship[^>]+Id="([^"]+)"[^>]+Target="([^"]+)"/', $relsXml, $m)) {
            foreach ($m[1] as $idx => $id) {
                if (strpos($m[2][$idx], 'media/') !== false) {
                    $imgMap[$id] = basename($m[2][$idx]);
                }
            }
        }
    }

    $html = '<div style="font-family:Arial,sans-serif; padding:20px; max-width:800px; margin:auto; background:#fff; color:#000;">';
    // Split by paragraphs
    $parts = preg_split('/<w:p\b[^>]*>/i', $docXml);
    array_shift($parts); // first chunk is before first <w:p>
    foreach ($parts as $paraXml) {
        $html .= '<p style="margin:0.5em 0;">';
        // Extract text runs
        if (preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $paraXml, $runs)) {
            foreach ($runs[1] as $t) $html .= htmlspecialchars($t);
        }
        // Embed images
        if (preg_match_all('/<wp:inline[^>]*>.*?<a:blip[^>]*r:embed="([^"]+)".*?<\/wp:inline>/s', $paraXml, $draw)) {
            foreach ($draw[1] as $embedId) {
                if (isset($imgMap[$embedId], $images[$imgMap[$embedId]])) {
                    $html .= '<br><img src="' . $images[$imgMap[$embedId]] . '" style="max-width:100%; height:auto;" />';
                }
            }
        }
        $html .= '</p>';
    }
    $html .= '</div>';
    return $html;
}

// ---------------------------------------------------------------------------
// .xlsx → HTML  (simplified grid)
// ---------------------------------------------------------------------------
function xlsxToHtml(ZipArchive $zip): string {
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml && preg_match_all('/<t[^>]*>([^<]*)<\/t>/', $ssXml, $m)) {
        $sharedStrings = $m[1];
    }

    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$sheetXml) return '<p>No sheet found.</p>';

    $html = '<div style="font-family:Arial,sans-serif; padding:20px; max-width:100%; overflow-x:auto; background:#fff; color:#000;">';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:auto;">';

    // Match rows
    if (preg_match_all('/<row[^>]*>(.*?)<\/row>/s', $sheetXml, $rows)) {
        foreach ($rows[1] as $rowXml) {
            $html .= '<tr>';
            // Match cells with optional type and value
            if (preg_match_all('/<c[^>]*t="([^"]*)"[^>]*>(.*?)<\/c>/s', $rowXml, $cells, PREG_SET_ORDER)) {
                foreach ($cells as $cell) {
                    $type = $cell[1];
                    $innerXml = $cell[2];
                    $v = '';
                    if (preg_match('/<v>(.*?)<\/v>/', $innerXml, $vm)) {
                        $v = $vm[1];
                    }
                    if ($type === 's' && isset($sharedStrings[(int)$v])) {
                        $displayValue = $sharedStrings[(int)$v];
                    } else {
                        $displayValue = $v;
                    }
                    $html .= '<td>' . htmlspecialchars($displayValue) . '</td>';
                }
            } else {
                $html .= '<td></td>';
            }
            $html .= '</tr>';
        }
    }
    $html .= '</table></div>';
    return $html;
}

// ---------------------------------------------------------------------------
// .pptx → HTML  (one slide after another)
// ---------------------------------------------------------------------------
function pptxToHtml(ZipArchive $zip): string {
    $html = '<div style="font-family:Arial,sans-serif; padding:10px; background:#f5f5f5;">';
    $slideIndex = 1;
    while (($xml = $zip->getFromName("ppt/slides/slide{$slideIndex}.xml")) !== false) {
        $slideText = '';
        if (preg_match_all('/<a:t[^>]*>([^<]*)<\/a:t>/', $xml, $m)) {
            $slideText = implode(' ', $m[1]);
        }
        $html .= '<div style="background:#fff; margin:20px auto; padding:20px; border-radius:8px; max-width:960px;">';
        $html .= '<h4>Slide ' . $slideIndex . '</h4>';
        $html .= '<p>' . htmlspecialchars(trim($slideText)) . '</p>';
        $html .= '</div>';
        $slideIndex++;
    }
    $html .= '</div>';
    return $html;
}

// ---------------------------------------------------------------------------
// Plain text extraction for AI / Read Aloud (regex‑based)
// ---------------------------------------------------------------------------
function extractPlainTextFromZip(ZipArchive $zip, string $mimeType): string {
    $text = '';
    if (in_array($mimeType, [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword'
    ])) {
        $xml = $zip->getFromName('word/document.xml');
        if ($xml && preg_match_all('/<w:t[^>]*>([^<]*)<\/w:t>/', $xml, $m)) {
            $text = implode(' ', $m[1]);
        }
    } elseif (in_array($mimeType, [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel'
    ])) {
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml && preg_match_all('/<t[^>]*>([^<]*)<\/t>/', $ssXml, $m)) {
            $sharedStrings = $m[1];
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml) {
            $lines = [];
            if (preg_match_all('/<c[^>]*t="s"[^>]*><v>(.*?)<\/v><\/c>/', $sheetXml, $cells)) {
                foreach ($cells[1] as $v) {
                    $lines[] = $sharedStrings[(int)$v] ?? '';
                }
            }
            $text = implode("\t", $lines);
        }
    } elseif (in_array($mimeType, [
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-powerpoint'
    ])) {
        $slideIndex = 1;
        while (($xml = $zip->getFromName("ppt/slides/slide{$slideIndex}.xml")) !== false) {
            $slideText = '';
            if (preg_match_all('/<a:t[^>]*>([^<]*)<\/a:t>/', $xml, $m)) {
                $slideText = implode(' ', $m[1]);
            }
            $text .= "Slide {$slideIndex}: " . trim($slideText) . "\n";
            $slideIndex++;
        }
    }
    return $text;
}