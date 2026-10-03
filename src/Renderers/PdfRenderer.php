<?php

namespace EazyReport\Renderers;

use EazyReport\Engine\PlacedBandRecord;
use EazyReport\Engine\PlacedElementRecord;
use EazyReport\Engine\RenderedPage;
use EazyReport\Expressions\Formatters;

class PdfRenderer
{
    private const MM_TO_PT = 2.83464567; // 72 / 25.4

    /**
     * Render the given pages to PDF binary string.
     *
     * @param RenderedPage[] $pages
     * @param array $options
     * @return string PDF binary bytes
     */
    public static function render(array $pages, array $options = []): string
    {
        // 1. If Dompdf is available and not explicitly disabled, use Dompdf for full SVG/HTML fidelity
        $preferNative = (bool)($options['native'] ?? false);
        if (!$preferNative && class_exists('\Dompdf\Dompdf')) {
            return self::renderWithDompdf($pages, $options);
        }

        // 2. Pure PHP Vector PDF 1.4 Generator (Zero Dependencies)
        return self::renderNativePdf($pages, $options);
    }

    /**
     * Render using Dompdf if installed.
     */
    public static function renderWithDompdf(array $pages, array $options = []): string
    {
        /** @var \Dompdf\Dompdf $dompdf */
        $dompdfClass = '\Dompdf\Dompdf';
        $optionsClass = '\Dompdf\Options';

        if (class_exists($optionsClass)) {
            $dompdfOptions = new $optionsClass();
            $dompdfOptions->set('isHtml5ParserEnabled', true);
            $dompdfOptions->set('isRemoteEnabled', true);
            $dompdf = new $dompdfClass($dompdfOptions);
        } else {
            $dompdf = new $dompdfClass();
        }

        $html = HtmlRenderer::render($pages, array_merge($options, ['standalone' => true]));
        $dompdf->loadHtml($html);

        $firstPage = $pages[0] ?? null;
        $pwPt = ($firstPage ? $firstPage->width : 210.0) * self::MM_TO_PT;
        $phPt = ($firstPage ? $firstPage->height : 297.0) * self::MM_TO_PT;

        $dompdf->setPaper([0, 0, $pwPt, $phPt]);
        $dompdf->render();

        return (string)$dompdf->output();
    }

    /**
     * Pure PHP Vector PDF generator. Generates valid PDF 1.4 streams directly.
     */
    public static function renderNativePdf(array $pages, array $options = []): string
    {
        $objects = [];
        $objCount = 0;

        $addObj = function (string $content) use (&$objects, &$objCount): int {
            $objCount++;
            $objects[$objCount] = $content;
            return $objCount;
        };

        // Obj 1: Catalog
        // Obj 2: Pages tree
        // Pages and Fonts will follow
        $fontObj = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");
        $fontBoldObj = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>");
        $fontItalicObj = $addObj("<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >>");

        $pageObjIds = [];
        $pageStreams = [];

        foreach ($pages as $page) {
            $pwPt = $page->width * self::MM_TO_PT;
            $phPt = $page->height * self::MM_TO_PT;

            $streamContent = "q\n";

            // Draw bands & elements
            foreach ($page->placedBands as $bandRecord) {
                $streamContent .= self::renderBandNative($bandRecord, $phPt);
            }

            // Draw Watermark
            if ($page->watermark->enabled && $page->watermark->text !== '') {
                $streamContent .= self::renderWatermarkNative($page->watermark, $pwPt, $phPt);
            }

            $streamContent .= "Q\n";

            $streamObjId = $addObj(sprintf("<< /Length %d >>\nstream\n%s\nendstream", strlen($streamContent), $streamContent));
            $pageStreams[] = $streamObjId;
        }

        // Pages Tree Obj placeholder (will fill in later)
        $pagesTreeObjId = $addObj("");

        foreach ($pages as $idx => $page) {
            $pwPt = $page->width * self::MM_TO_PT;
            $phPt = $page->height * self::MM_TO_PT;
            $streamId = $pageStreams[$idx];

            $pageObj = sprintf(
                "<< /Type /Page /Parent %d 0 R /MediaBox [0 0 %.2f %.2f] /Contents %d 0 R /Resources << /Font << /F1 %d 0 R /F2 %d 0 R /F3 %d 0 R >> >> >>",
                $pagesTreeObjId,
                $pwPt,
                $phPt,
                $streamId,
                $fontObj,
                $fontBoldObj,
                $fontItalicObj
            );
            $pageObjIds[] = $addObj($pageObj);
        }

        // Update Pages Tree object
        $kidsStr = implode(' ', array_map(fn($id) => "{$id} 0 R", $pageObjIds));
        $objects[$pagesTreeObjId] = sprintf("<< /Type /Pages /Kids [%s] /Count %d >>", $kidsStr, count($pageObjIds));

        // Catalog Obj
        $catalogObjId = $addObj(sprintf("<< /Type /Catalog /Pages %d 0 R >>", $pagesTreeObjId));

        // Assemble PDF byte stream
        $out = "%PDF-1.4\n%\xe2\xe3\xcf\xd3\n";
        $xrefOffsets = [0];

        for ($i = 1; $i <= $objCount; $i++) {
            $xrefOffsets[$i] = strlen($out);
            $out .= sprintf("%d 0 obj\n%s\nendobj\n", $i, $objects[$i]);
        }

        $xrefStart = strlen($out);
        $out .= sprintf("xref\n0 %d\n0000000000 65535 f \n", $objCount + 1);
        for ($i = 1; $i <= $objCount; $i++) {
            $out .= sprintf("%010d 00000 n \n", $xrefOffsets[$i]);
        }

        $out .= sprintf(
            "trailer\n<< /Size %d /Root %d 0 R >>\nstartxref\n%d\n%%%%EOF\n",
            $objCount + 1,
            $catalogObjId,
            $xrefStart
        );

        return $out;
    }

    private static function renderBandNative(PlacedBandRecord $band, float $pageHeightPt): string
    {
        $out = '';
        $bxPt = $band->x * self::MM_TO_PT;
        $byPt = $band->y * self::MM_TO_PT;
        $bwPt = $band->width * self::MM_TO_PT;
        $bhPt = $band->height * self::MM_TO_PT;

        // Band Background
        if ($band->fill !== '' && $band->fill !== 'transparent') {
            $rgb = self::parseHexColor($band->fill);
            if ($rgb !== null) {
                $pdfY = $pageHeightPt - $byPt - $bhPt;
                $out .= sprintf("%.3f %.3f %.3f rg\n%.2f %.2f %.2f %.2f re f\n", $rgb[0], $rgb[1], $rgb[2], $bxPt, $pdfY, $bwPt, $bhPt);
            }
        }

        // Elements
        foreach ($band->elements as $placedEl) {
            $out .= self::renderElementNative($placedEl, $bxPt, $byPt, $pageHeightPt);
        }

        return $out;
    }

    private static function renderElementNative(PlacedElementRecord $placed, float $bandLeftPt, float $bandTopPt, float $pageHeightPt): string
    {
        $el = $placed->element;
        $xPt = $bandLeftPt + ($placed->x * self::MM_TO_PT);
        $yPt = $bandTopPt + ($placed->y * self::MM_TO_PT);
        $wPt = $placed->w * self::MM_TO_PT;
        $hPt = $placed->h * self::MM_TO_PT;
        $pdfY = $pageHeightPt - $yPt - $hPt;

        $out = '';

        // Box background
        if ($el->box->background !== '' && $el->box->background !== 'transparent') {
            $bgRgb = self::parseHexColor($el->box->background);
            if ($bgRgb !== null) {
                $out .= sprintf("%.3f %.3f %.3f rg\n%.2f %.2f %.2f %.2f re f\n", $bgRgb[0], $bgRgb[1], $bgRgb[2], $xPt, $pdfY, $wPt, $hPt);
            }
        }

        // Box border
        if ($el->box->borderWidth > 0 && $el->box->borderColor !== '') {
            $borderRgb = self::parseHexColor($el->box->borderColor);
            if ($borderRgb !== null) {
                $bw = max(0.5, $el->box->borderWidth * self::MM_TO_PT);
                $out .= sprintf("%.3f %.3f %.3f RG\n%.2f w\n%.2f %.2f %.2f %.2f re S\n", $borderRgb[0], $borderRgb[1], $borderRgb[2], $bw, $xPt, $pdfY, $wPt, $hPt);
            }
        }

        // Shape/Line
        $type = strtolower($el->type ?: 'text');
        if ($type === 'shape' || $type === 'line') {
            $shape = strtolower($el->shapeType ?: 'rectangle');
            if ($shape === 'line') {
                $strokeRgb = self::parseHexColor($el->stroke ?: ($el->box->borderColor ?: '#000000')) ?? [0, 0, 0];
                $sw = max(0.5, ($el->strokeWidth > 0 ? $el->strokeWidth : $el->box->borderWidth) * self::MM_TO_PT);
                $lineY = $pdfY + ($hPt / 2);
                $out .= sprintf("%.3f %.3f %.3f RG\n%.2f w\n%.2f %.2f m %.2f %.2f l S\n", $strokeRgb[0], $strokeRgb[1], $strokeRgb[2], $sw, $xPt, $lineY, $xPt + $wPt, $lineY);
            }
            return $out;
        }

        // Text element
        if ($placed->text !== '') {
            $fontTag = '/F1';
            if ($el->style->bold) {
                $fontTag = '/F2';
            } elseif ($el->style->italic) {
                $fontTag = '/F3';
            }

            $fontSize = max(4.0, $el->style->fontSize);
            $colorRgb = self::parseHexColor($el->style->color) ?? [0, 0, 0];

            $lines = explode("\n", $placed->text);
            $lineH = $fontSize * $el->style->lineHeight;

            $padTop = $el->box->getPaddingTop() * self::MM_TO_PT;
            $padLeft = $el->box->getPaddingLeft() * self::MM_TO_PT;

            $textY = $pdfY + $hPt - $padTop - $fontSize;

            foreach ($lines as $lineIdx => $line) {
                $curY = $textY - ($lineIdx * $lineH);
                if ($curY < $pdfY) break;

                $curX = $xPt + $padLeft;
                // PDF escaping: \ -> \\, ( -> \(, ) -> \)
                $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);

                $out .= sprintf(
                    "BT\n%s %.1f Tf\n%.3f %.3f %.3f rg\n1 0 0 1 %.2f %.2f Tm\n(%s) Tj\nET\n",
                    $fontTag,
                    $fontSize,
                    $colorRgb[0],
                    $colorRgb[1],
                    $colorRgb[2],
                    $curX,
                    $curY,
                    $escaped
                );
            }
        }

        return $out;
    }

    private static function renderWatermarkNative(\EazyReport\Model\Watermark $wm, float $pwPt, float $phPt): string
    {
        $fontSize = max(12.0, $wm->fontSize);
        $rgb = self::parseHexColor($wm->color) ?? [0.8, 0.8, 0.8];
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $wm->text);

        $rad = deg2rad($wm->rotation);
        $cos = cos($rad);
        $sin = sin($rad);

        $cx = $pwPt / 2;
        $cy = $phPt / 2;

        return sprintf(
            "q\nBT\n/F2 %.1f Tf\n%.3f %.3f %.3f rg\n%.4f %.4f %.4f %.4f %.2f %.2f Tm\n(%s) Tj\nET\nQ\n",
            $fontSize,
            $rgb[0],
            $rgb[1],
            $rgb[2],
            $cos,
            $sin,
            -$sin,
            $cos,
            $cx - 100,
            $cy,
            $escaped
        );
    }

    private static function parseHexColor(string $hex): ?array
    {
        $h = trim($hex);
        if ($h === '' || $h === 'transparent') return null;
        if ($h[0] === '#') $h = substr($h, 1);

        if (strlen($h) === 3) {
            $r = hexdec($h[0] . $h[0]) / 255.0;
            $g = hexdec($h[1] . $h[1]) / 255.0;
            $b = hexdec($h[2] . $h[2]) / 255.0;
            return [$r, $g, $b];
        }

        if (strlen($h) >= 6) {
            $r = hexdec(substr($h, 0, 2)) / 255.0;
            $g = hexdec(substr($h, 2, 2)) / 255.0;
            $b = hexdec(substr($h, 4, 2)) / 255.0;
            return [$r, $g, $b];
        }

        return null;
    }
}
