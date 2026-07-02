<?php
/**
 * Office Document Preview Converter – Pure PHP (no extensions)
 * .docx, .xlsx, .pptx → HTML with text and images
 *
 * Uses native PHP ZipArchive – fully compatible with the corrected
 * ai_document.php text extraction.
 */

// -----------------------------------------------------------------------------
// Image helper
// -----------------------------------------------------------------------------
function getImageMime(string $imgData): string {
    $head = substr($imgData, 0, 8);
    if (strpos($head, "\x89PNG") === 0) return 'image/png';
    if (strpos($head, "\xFF\xD8\xFF") === 0) return 'image/jpeg';
    if (strpos($head, "GIF89a") === 0 || strpos($head, "GIF87a") === 0) return 'image/gif';
    return 'image/png';
}

// -----------------------------------------------------------------------------
// Extract all images from a ZIP office file (used for preview)
// -----------------------------------------------------------------------------
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

// -----------------------------------------------------------------------------
// Main entrypoint: convert Office file to HTML preview
// -----------------------------------------------------------------------------
function officeFileToHtml(string $filePath, string $mimeType): string|false {
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        return false;
    }

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

// -----------------------------------------------------------------------------
// .docx → HTML
// -----------------------------------------------------------------------------
function docxToHtml(ZipArchive $zip): string {
    $docXml = $zip->getFromName('word/document.xml');
    if (!$docXml) return '<p>Invalid document.</p>';

    $images = extractImagesArray($zip, 'word/media/');
    $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
    $imgMap  = [];
    if ($relsXml) {
        $relsDom = new DOMDocument();
        $relsDom->loadXML($relsXml);
        foreach ($relsDom->getElementsByTagName('Relationship') as $rel) {
            if ($rel instanceof DOMElement) {
                if (strpos($rel->getAttribute('Type'), 'image') !== false) {
                    $target = $rel->getAttribute('Target');
                    $imgMap[$rel->getAttribute('Id')] = basename($target);
                }
            }
        }
    }

    $dom = new DOMDocument();
    $dom->loadXML($docXml);
    $html = '<div style="font-family:Arial,sans-serif; padding:20px; max-width:800px; margin:auto; background:#fff; color:#000;">';
    $body = $dom->getElementsByTagName('body')->item(0);
    if ($body) {
        foreach ($body->childNodes as $child) {
            if ($child->nodeName === 'p' && $child instanceof DOMElement) {
                $html .= '<p style="margin:0.5em 0;">';
                foreach ($child->childNodes as $runOrDrawing) {
                    if ($runOrDrawing->nodeName === 'r' && $runOrDrawing instanceof DOMElement) {
                        $tList = $runOrDrawing->getElementsByTagName('t');
                        if ($tList->length > 0) {
                            $text = '';
                            foreach ($tList as $tNode) $text .= $tNode->nodeValue;
                            $html .= htmlspecialchars($text);
                        }
                    } elseif ($runOrDrawing->nodeName === 'drawing' && $runOrDrawing instanceof DOMElement) {
                        $blipList = $runOrDrawing->getElementsByTagName('a:blip');
                        if ($blipList->length > 0) {
                            $blipItem = $blipList->item(0);
                            if ($blipItem instanceof DOMElement) {
                                $embedId = $blipItem->getAttribute('r:embed');
                                if (isset($imgMap[$embedId])) {
                                    $imgFile = $imgMap[$embedId];
                                    if (isset($images[$imgFile])) {
                                        $html .= '<img src="' . $images[$imgFile] . '" style="max-width:100%; height:auto;" />';
                                    }
                                }
                            }
                        }
                    }
                }
                $html .= '</p>';
            } elseif ($child->nodeName === 'tbl' && $child instanceof DOMElement) {
                $html .= docxTableToHtml($child);
            }
        }
    }
    $html .= '</div>';
    return $html;
}

function docxTableToHtml(DOMElement $tbl): string {
    $html = '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:100%; margin:1em 0;">';
    $rows = $tbl->getElementsByTagName('tr');
    foreach ($rows as $row) {
        if (!($row instanceof DOMElement)) continue;
        $html .= '<tr>';
        $cells = $row->getElementsByTagName('tc');
        foreach ($cells as $cell) {
            if (!($cell instanceof DOMElement)) continue;
            $text = '';
            $pNodes = $cell->getElementsByTagName('p');
            foreach ($pNodes as $p) {
                if (!($p instanceof DOMElement)) continue;
                $tNodes = $p->getElementsByTagName('t');
                foreach ($tNodes as $t) $text .= $t->nodeValue;
                $text .= ' ';
            }
            $html .= '<td>' . htmlspecialchars(trim($text)) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table>';
    return $html;
}

// -----------------------------------------------------------------------------
// .xlsx → HTML
// -----------------------------------------------------------------------------
function xlsxToHtml(ZipArchive $zip): string {
    $sharedStrings = [];
    $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $ssDom = new DOMDocument();
        $ssDom->loadXML($ssXml);
        foreach ($ssDom->getElementsByTagName('si') as $si) {
            if (!($si instanceof DOMElement)) continue;
            $tList = $si->getElementsByTagName('t');
            $sharedStrings[] = $tList->length > 0 ? $tList->item(0)->nodeValue : '';
        }
    }
    $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    if (!$sheetXml) return '<p>No sheet found.</p>';
    $dom = new DOMDocument();
    $dom->loadXML($sheetXml);
    $rows = $dom->getElementsByTagName('row');
    $html = '<div style="font-family:Arial,sans-serif; padding:20px; max-width:100%; overflow-x:auto; background:#fff; color:#000;">';
    $html .= '<table border="1" cellpadding="5" cellspacing="0" style="border-collapse:collapse; width:auto;">';
    foreach ($rows as $row) {
        if (!($row instanceof DOMElement)) continue;
        $html .= '<tr>';
        $cells = $row->getElementsByTagName('c');
        foreach ($cells as $cell) {
            if (!($cell instanceof DOMElement)) continue;
            $value = '';
            $vList = $cell->getElementsByTagName('v');
            if ($vList->length > 0) {
                $val = $vList->item(0)->nodeValue;
                $type = $cell->getAttribute('t');
                if ($type === 's' && isset($sharedStrings[(int)$val])) {
                    $value = $sharedStrings[(int)$val];
                } else {
                    $value = $val;
                }
            }
            $html .= '<td>' . htmlspecialchars($value) . '</td>';
        }
        $html .= '</tr>';
    }
    $html .= '</table></div>';
    return $html;
}

// -----------------------------------------------------------------------------
// .pptx → HTML
// -----------------------------------------------------------------------------
function pptxToHtml(ZipArchive $zip): string {
    $html = '<div style="font-family:Arial,sans-serif; padding:10px; background:#f5f5f5;">';
    $slideIndex = 1;
    while (($xml = $zip->getFromName("ppt/slides/slide{$slideIndex}.xml")) !== false) {
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $tNodes = $dom->getElementsByTagName('t');
        $slideText = '';
        foreach ($tNodes as $t) $slideText .= $t->nodeValue . ' ';
        $html .= '<div style="background:#fff; margin:20px auto; padding:20px; border-radius:8px; max-width:960px;">';
        $html .= '<h4>Slide ' . $slideIndex . '</h4>';
        $html .= '<p>' . htmlspecialchars(trim($slideText)) . '</p>';
        $html .= '</div>';
        $slideIndex++;
    }
    $html .= '</div>';
    return $html;
}

// -----------------------------------------------------------------------------
// Plain text extraction for AI (used by ai_document.php)
// -----------------------------------------------------------------------------
function extractPlainTextFromZip(ZipArchive $zip, string $mimeType): string {
    $text = '';
    if (in_array($mimeType, [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword'
    ])) {
        $xml = $zip->getFromName('word/document.xml');
        if ($xml) {
            $dom = new DOMDocument();
            $dom->loadXML($xml);
            foreach ($dom->getElementsByTagName('p') as $p) {
                if (!($p instanceof DOMElement)) continue;
                $line = '';
                foreach ($p->getElementsByTagName('r') as $r) {
                    if (!($r instanceof DOMElement)) continue;
                    foreach ($r->getElementsByTagName('t') as $t) $line .= $t->nodeValue;
                }
                $text .= trim($line) . "\n";
            }
        }
    } elseif (in_array($mimeType, [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel'
    ])) {
        $sharedStrings = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $ssDom = new DOMDocument();
            $ssDom->loadXML($ssXml);
            foreach ($ssDom->getElementsByTagName('si') as $si) {
                if (!($si instanceof DOMElement)) continue;
                $tList = $si->getElementsByTagName('t');
                $sharedStrings[] = $tList->length > 0 ? $tList->item(0)->nodeValue : '';
            }
        }
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml) {
            $dom = new DOMDocument();
            $dom->loadXML($sheetXml);
            foreach ($dom->getElementsByTagName('row') as $row) {
                if (!($row instanceof DOMElement)) continue;
                $cells = $row->getElementsByTagName('c');
                $rowText = [];
                foreach ($cells as $cell) {
                    if (!($cell instanceof DOMElement)) continue;
                    $vList = $cell->getElementsByTagName('v');
                    if ($vList->length > 0) {
                        $val = $vList->item(0)->nodeValue;
                        $type = $cell->getAttribute('t');
                        if ($type === 's' && isset($sharedStrings[(int)$val])) {
                            $rowText[] = $sharedStrings[(int)$val];
                        } else {
                            $rowText[] = $val;
                        }
                    }
                }
                $text .= implode("\t", $rowText) . "\n";
            }
        }
    } elseif (in_array($mimeType, [
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'application/vnd.ms-powerpoint'
    ])) {
        $slideIndex = 1;
        while (($xml = $zip->getFromName("ppt/slides/slide{$slideIndex}.xml")) !== false) {
            $dom = new DOMDocument();
            $dom->loadXML($xml);
            $slideText = '';
            foreach ($dom->getElementsByTagName('t') as $t) $slideText .= $t->nodeValue . ' ';
            $text .= "Slide {$slideIndex}: " . trim($slideText) . "\n";
            $slideIndex++;
        }
    }
    return $text;
}