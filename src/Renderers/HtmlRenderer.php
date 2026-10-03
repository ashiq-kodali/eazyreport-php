<?php

namespace EazyReport\Renderers;

use EazyReport\Engine\RenderedPage;
use EazyReport\Expressions\Formatters;
use EazyReport\Model\Watermark;

class HtmlRenderer
{
    /**
     * @param RenderedPage[] $pages
     * @param array $options
     * @return string
     */
    public static function render(array $pages, array $options = []): string
    {
        $standalone = (bool)($options['standalone'] ?? true);
        $title = (string)($options['title'] ?? 'EazyReport');
        $zoom = (float)($options['zoom'] ?? 1.0);

        $bodyContent = '';
        foreach ($pages as $index => $page) {
            $bodyContent .= self::renderPage($page, $index + 1, count($pages));
        }

        if (!$standalone) {
            return "<div class=\"eazyreport-container\" style=\"display:flex;flex-direction:column;align-items:center;gap:20px;\">{$bodyContent}</div>";
        }

        $firstPage = $pages[0] ?? null;
        $pw = $firstPage ? $firstPage->width : 210.0;
        $ph = $firstPage ? $firstPage->height : 297.0;

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>
        @page {
            size: {$pw}mm {$ph}mm;
            margin: 0;
        }
        *, *::before, *::after {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }
        body {
            margin: 0;
            padding: 0;
            background-color: #f1f5f9;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 24px;
            padding: 24px 0;
        }
        .eazyreport-page {
            position: relative;
            background-color: #ffffff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            page-break-after: always;
            break-after: page;
        }
        .eazyreport-page:last-child {
            page-break-after: avoid;
            break-after: avoid;
        }
        .eazyreport-watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            pointer-events: none;
            z-index: 0;
            user-select: none;
            white-space: nowrap;
        }
        .eazyreport-content {
            position: relative;
            width: 100%;
            height: 100%;
            z-index: 1;
        }
        @media print {
            body {
                background-color: #ffffff !important;
                padding: 0 !important;
                gap: 0 !important;
            }
            .eazyreport-page {
                box-shadow: none !important;
                margin: 0 !important;
            }
        }
    </style>
</head>
<body>
{$bodyContent}
</body>
</html>
HTML;
    }

    private static function renderPage(RenderedPage $page, int $pageNumber, int $totalPages): string
    {
        $wmHtml = self::renderWatermark($page->watermark);

        return sprintf(
            '<div class="eazyreport-page" data-page="%d" style="width:%.2fmm;height:%.2fmm;min-width:%.2fmm;min-height:%.2fmm;">' .
            '%s<div class="eazyreport-content">%s</div></div>',
            $pageNumber,
            $page->width,
            $page->height,
            $page->width,
            $page->height,
            $wmHtml,
            $page->html
        );
    }

    private static function renderWatermark(Watermark $wm): string
    {
        if (!$wm->enabled) {
            return '';
        }

        if ($wm->type === 'image' && $wm->imageSrc !== '') {
            $src = htmlspecialchars($wm->imageSrc, ENT_QUOTES);
            return sprintf(
                '<div class="eazyreport-watermark" style="opacity:%.2f;transform:translate(-50%%, -50%%) rotate(%.1fdeg);">' .
                '<img src="%s" style="max-width:80%%;max-height:80%%;object-fit:contain;" /></div>',
                $wm->opacity,
                $wm->rotation,
                $src
            );
        }

        if ($wm->text !== '') {
            $escText = Formatters::escapeHtml($wm->text);
            return sprintf(
                '<div class="eazyreport-watermark" style="opacity:%.2f;transform:translate(-50%%, -50%%) rotate(%.1fdeg);' .
                'font-size:%.1fpt;color:%s;font-weight:bold;font-family:sans-serif;letter-spacing:4px;">%s</div>',
                $wm->opacity,
                $wm->rotation,
                $wm->fontSize,
                $wm->color,
                $escText
            );
        }

        return '';
    }
}
