<?php
// Shared text/math shorthand for Question Paper & Subjective Paper authoring.
// Ports the source's convertLatexToMathObjects/parseToRuns (duplicated 3x
// there, once per export site) into one canonical implementation used by
// both features' DOCX generators.
//
// Shorthand: $...$/$$...$$ wrap a LaTeX-subset formula (\frac \sqrt ^ _ and a
// small symbol table); outside math, **bold** *italic* __underline__
// ^^sup^^ ,,sub,, and a leading "- "/"1. " list marker are recognized.
//
// PHPWord's bundled phpoffice/math package can only render Fraction/Row/
// Identifier/Numeric/Operator as real OOXML (no Radical, and Superscript
// exists but isn't wired into its writer) — not enough for \sqrt/^/_. So
// instead of going through PhpWord\Element\Formula, every formula is hand-
// converted straight to raw <m:oMath> OOXML text here, written into the
// document as a placeholder run, and swapped in by string replacement on
// word/document.xml after PHPWord saves (paper_stream_docx below) — the
// only way to get real, editable Word equations for the full LaTeX subset
// this feature needs out of the installed library.

// ---- LaTeX-subset -> element tree (mirrors convertLatexToMathObjects) ----

function paper_parse_latex(string $latex): array {
    $result = [];
    $i = 0;
    $len = strlen($latex);

    $parseGroup = function () use (&$i, &$latex, $len) {
        if (!isset($latex[$i]) || $latex[$i] !== '{') {
            $ch = $latex[$i] ?? '';
            $i++;
            return $ch;
        }
        $content = '';
        $balance = 1;
        $i++;
        while ($i < $len && $balance > 0) {
            if ($latex[$i] === '{') {
                $balance++;
            } elseif ($latex[$i] === '}') {
                $balance--;
            }
            if ($balance > 0) {
                $content .= $latex[$i];
            }
            $i++;
        }
        return $content;
    };

    $symbolMap = [
        'times' => '×', 'div' => '÷', 'pm' => '±', 'neq' => '≠', 'approx' => '≈',
        'leq' => '≤', 'geq' => '≥', 'pi' => 'π', 'theta' => 'θ', 'int' => '∫', 'sum' => 'Σ',
    ];

    while ($i < $len) {
        $next = $latex[$i + 1] ?? '';
        if (substr($latex, $i, 5) === '\\frac') {
            $i += 5;
            $num = $parseGroup();
            $den = $parseGroup();
            $result[] = ['type' => 'frac', 'num' => paper_parse_latex($num), 'den' => paper_parse_latex($den)];
        } elseif (substr($latex, $i, 5) === '\\sqrt') {
            $i += 5;
            $degree = null;
            if (($latex[$i] ?? '') === '[') {
                $i++;
                $degContent = '';
                while ($i < $len && $latex[$i] !== ']') {
                    $degContent .= $latex[$i];
                    $i++;
                }
                $i++; // skip ']'
                $degree = $degContent !== '' ? paper_parse_latex($degContent) : null;
            }
            $base = $parseGroup();
            $result[] = ['type' => 'radical', 'degree' => $degree, 'base' => paper_parse_latex($base)];
        } elseif ($latex[$i] === '^' && ($next === '{' || preg_match('/[0-9a-zA-Z]/', $next))) {
            $prev = array_pop($result);
            $i++;
            $sup = $parseGroup();
            $result[] = ['type' => 'sup', 'base' => $prev, 'sup' => paper_parse_latex($sup)];
        } elseif ($latex[$i] === '_' && ($next === '{' || preg_match('/[0-9a-zA-Z]/', $next))) {
            $prev = array_pop($result);
            $i++;
            $sub = $parseGroup();
            $result[] = ['type' => 'sub', 'base' => $prev, 'sub' => paper_parse_latex($sub)];
        } else {
            $text = $latex[$i];
            if ($text === '\\') {
                $cmd = '';
                $j = $i + 1;
                while ($j < $len && preg_match('/[a-zA-Z]/', $latex[$j])) {
                    $cmd .= $latex[$j];
                    $j++;
                }
                if (isset($symbolMap[$cmd])) {
                    $text = $symbolMap[$cmd];
                    $i = $j - 1;
                }
            }
            $result[] = ['type' => 'run', 'text' => $text];
            $i++;
        }
    }
    return $result;
}

// ---- element tree -> raw OOXML (Office Math Markup Language) ----

function paper_math_element_to_omml($el): string {
    if ($el === null) {
        return '<m:r><m:t xml:space="preserve"></m:t></m:r>';
    }
    switch ($el['type']) {
        case 'run':
            return '<m:r><m:t xml:space="preserve">' . htmlspecialchars($el['text'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</m:t></m:r>';
        case 'frac':
            return '<m:f><m:num>' . paper_math_elements_to_omml($el['num']) . '</m:num><m:den>' . paper_math_elements_to_omml($el['den']) . '</m:den></m:f>';
        case 'radical':
            $degHide = $el['degree'] === null ? '1' : '0';
            $degXml = $el['degree'] === null ? '' : paper_math_elements_to_omml($el['degree']);
            return '<m:rad><m:radPr><m:degHide m:val="' . $degHide . '"/></m:radPr><m:deg>' . $degXml . '</m:deg><m:e>' . paper_math_elements_to_omml($el['base']) . '</m:e></m:rad>';
        case 'sup':
            return '<m:sSup><m:e>' . paper_math_element_to_omml($el['base']) . '</m:e><m:sup>' . paper_math_elements_to_omml($el['sup']) . '</m:sup></m:sSup>';
        case 'sub':
            return '<m:sSub><m:e>' . paper_math_element_to_omml($el['base']) . '</m:e><m:sub>' . paper_math_elements_to_omml($el['sub']) . '</m:sub></m:sSub>';
    }
    return '';
}

function paper_math_elements_to_omml(array $elements): string {
    $xml = '';
    foreach ($elements as $el) {
        $xml .= paper_math_element_to_omml($el);
    }
    return $xml;
}

function paper_latex_to_omml(string $latex): string {
    $elements = paper_parse_latex($latex);
    return '<m:oMath xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math">' . paper_math_elements_to_omml($elements) . '</m:oMath>';
}

// ---- shorthand text -> a flat list of runs (mirrors parseToRuns) ----
// Each op is either ['math'=>latexFormula] or
// ['text'=>string,'bold'|'italic'|'underline'|'sup'|'sub'=>true,'break'=>bool].

function paper_shorthand_to_runs(string $text): array {
    if ($text === '') {
        return [];
    }
    $result = [];
    $lines = preg_split('/\r\n|\r|\n/', $text);

    foreach ($lines as $lineIdx => $line) {
        $linePrefix = '';
        $currentLine = $line;
        if (str_starts_with($line, '- ')) {
            $linePrefix = '• ';
            $currentLine = substr($line, 2);
        } elseif (preg_match('/^(\d+)\.\s/', $line, $m)) {
            $linePrefix = $m[0];
            $currentLine = substr($line, strlen($m[0]));
        }

        $segments = preg_split('/(\$\$.*?\$\$|\$.*?\$)/s', $currentLine, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($segments as $segIdx => $segment) {
            if ($segment === '' && $segIdx !== 0) {
                continue;
            }
            $isFirstPart = $segIdx === 0;
            $finalPrefix = $isFirstPart ? $linePrefix : '';

            if ($segment !== '' && $segment[0] === '$') {
                $formula = trim(str_replace('$', '', $segment));
                if ($formula !== '') {
                    if ($finalPrefix !== '') {
                        $result[] = ['text' => $finalPrefix, 'break' => $lineIdx > 0];
                    }
                    $result[] = ['math' => $formula, 'break' => $finalPrefix === '' && $lineIdx > 0 && $isFirstPart];
                }
                continue;
            }

            $subSegments = preg_split('/(\*\*.*?\*\*|\*.*?\*|__.*?__|\^\^.*?\^\^|,,.*?,,)/', $segment, -1, PREG_SPLIT_DELIM_CAPTURE);
            foreach ($subSegments as $subIdx => $sub) {
                if ($sub === '' && $subIdx !== 0) {
                    continue;
                }
                $isVeryFirst = $isFirstPart && $subIdx === 0;
                $breakVal = $isVeryFirst && $lineIdx > 0;
                $prefixToUse = $isVeryFirst ? $finalPrefix : '';

                if ($sub !== '' && preg_match('/^\*\*(.*)\*\*$/s', $sub, $m)) {
                    $result[] = ['text' => $prefixToUse . $m[1], 'bold' => true, 'break' => $breakVal];
                } elseif ($sub !== '' && preg_match('/^__(.*)__$/s', $sub, $m)) {
                    $result[] = ['text' => $prefixToUse . $m[1], 'underline' => true, 'break' => $breakVal];
                } elseif ($sub !== '' && preg_match('/^\^\^(.*)\^\^$/s', $sub, $m)) {
                    $result[] = ['text' => $prefixToUse . $m[1], 'sup' => true, 'break' => $breakVal];
                } elseif ($sub !== '' && preg_match('/^,,(.*),,$/s', $sub, $m)) {
                    $result[] = ['text' => $prefixToUse . $m[1], 'sub' => true, 'break' => $breakVal];
                } elseif ($sub !== '' && preg_match('/^\*(.*)\*$/s', $sub, $m)) {
                    $result[] = ['text' => $prefixToUse . $m[1], 'italic' => true, 'break' => $breakVal];
                } else {
                    $result[] = ['text' => $prefixToUse . $sub, 'break' => $breakVal];
                }
            }
        }
    }
    return $result;
}

// ---- writing runs into a PhpWord container (Section or TextRun) ----
// $mathMap accumulates token => <m:oMath> XML, consumed by paper_stream_docx.

function paper_write_runs($container, array $ops, array &$mathMap): void {
    static $counter = 0;
    foreach ($ops as $op) {
        if (!empty($op['break'])) {
            $container->addTextBreak();
        }
        if (isset($op['math'])) {
            $counter++;
            $token = "\u{E000}MATHFORMULA{$counter}\u{E001}";
            $mathMap[$token] = paper_latex_to_omml($op['math']);
            $container->addText($token, ['size' => 12]);
            continue;
        }
        $text = $op['text'];
        if ($text === '') {
            continue;
        }
        $style = ['size' => 12];
        if (!empty($op['bold'])) {
            $style['bold'] = true;
        }
        if (!empty($op['italic'])) {
            $style['italic'] = true;
        }
        if (!empty($op['underline'])) {
            $style['underline'] = \PhpOffice\PhpWord\Style\Font::UNDERLINE_SINGLE;
        }
        if (!empty($op['sup'])) {
            $style['superScript'] = true;
        }
        if (!empty($op['sub'])) {
            $style['subScript'] = true;
        }
        $container->addText($text, $style);
    }
}

// Convenience: parse shorthand text straight into a container.
function paper_write_shorthand($container, string $text, array &$mathMap): void {
    paper_write_runs($container, paper_shorthand_to_runs($text), $mathMap);
}

// Shared by both papers' DOCX generators: decode a base64 data-URL image, or
// return null on failure so the caller can skip it silently.
function paper_decode_base64_image($dataUrl) {
    $parts = explode(',', $dataUrl, 2);
    if (count($parts) !== 2) {
        return null;
    }
    $decoded = base64_decode($parts[1], true);
    return $decoded === false ? null : $decoded;
}

function paper_add_base64_image($section, $dataUrl, $width, $height, $alignment = null) {
    $bytes = paper_decode_base64_image($dataUrl);
    if ($bytes === null) {
        return;
    }
    $style = ['width' => $width, 'height' => $height];
    $paragraphStyle = [];
    if ($alignment !== null) {
        $paragraphStyle['alignment'] = $alignment;
    }
    $section->addImage($bytes, $style, $paragraphStyle ?: null);
}

// Saves $phpWord to a temp file, swaps every math placeholder run for its
// real <m:oMath> OOXML in word/document.xml, then streams it to the browser
// as an attachment and cleans up. $mathMap: token => oMath XML (see above).
function paper_stream_docx(\PhpOffice\PhpWord\PhpWord $phpWord, array $mathMap, string $filename): void {
    $tmpFile = tempnam(sys_get_temp_dir(), 'paper_docx_');
    $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
    $writer->save($tmpFile);

    if (!empty($mathMap)) {
        $zip = new ZipArchive();
        if ($zip->open($tmpFile) === true) {
            $xml = $zip->getFromName('word/document.xml');
            foreach ($mathMap as $token => $omml) {
                $pattern = '/<w:r>(?:(?!<w:r>|<\/w:r>)[\s\S])*?' . preg_quote($token, '/') . '(?:(?!<w:r>|<\/w:r>)[\s\S])*?<\/w:r>/';
                $xml = preg_replace($pattern, $omml, $xml, 1);
            }
            $zip->addFromString('word/document.xml', $xml);
            $zip->close();
        }
    }

    header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    header('Content-Length: ' . filesize($tmpFile));
    readfile($tmpFile);
    unlink($tmpFile);
}
