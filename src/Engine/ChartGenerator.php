<?php

namespace EazyReport\Engine;

use EazyReport\Expressions\Formatters;
use EazyReport\Model\LayoutElement;

class ChartGenerator
{
    public static function generateSvg(LayoutElement $el, array $labels, array $values): string
    {
        $w = $el->w * 10.0;
        $h = $el->h * 10.0;
        $pt = 3.528;
        $fs = $el->style->fontSize * $pt;
        $escFamily = Formatters::escapeHtml($el->style->fontFamily);
        $font = "font-family=\"'{$escFamily}', sans-serif\" fill=\"{$el->style->color}\"";

        $colors = !empty($el->colors) ? $el->colors : ['#3b82f6', '#10b981', '#f59e0b', '#ef4444'];
        $colorFn = fn($i) => $colors[$i % count($colors)];

        $parts = [];
        $top = 4.0;

        if ($el->title !== '') {
            $escTitle = Formatters::escapeHtml($el->title);
            $parts[] = "<text x=\"" . ($w / 2) . "\" y=\"" . ($top + $fs * 1.1) . "\" text-anchor=\"middle\" font-size=\"" . ($fs * 1.15) . "\" font-weight=\"700\" {$font}>{$escTitle}</text>";
            $top += $fs * 1.6;
        }

        if (empty($values)) {
            $parts[] = "<text x=\"" . ($w / 2) . "\" y=\"" . ($h / 2) . "\" text-anchor=\"middle\" font-size=\"{$fs}\" {$font}>No data</text>";
            return self::wrap($w, $h, $parts);
        }

        $chartType = strtolower($el->chartType ?: 'column');

        if (in_array($chartType, ['pie', 'doughnut'])) {
            $legendW = $el->showLegend ? min($w * 0.4, 400.0) : 0.0;
            $cx = ($w - $legendW) / 2.0;
            $cy = $top + ($h - $top) / 2.0;
            $r = max(10.0, min(($w - $legendW) / 2.0, ($h - $top) / 2.0) - 8.0);
            $total = array_sum(array_map(fn($v) => max(0.0, (float)$v), $values));
            if ($total == 0) $total = 1.0;

            $a0 = -M_PI / 2.0;
            for ($i = 0; $i < count($values); $i++) {
                $v = (float)$values[$i];
                $a1 = $a0 + (max(0.0, $v) / $total) * M_PI * 2.0;
                $large = ($a1 - $a0 > M_PI) ? 1 : 0;

                $pFn = fn($a, $rr) => sprintf('%.2f,%.2f', $cx + $rr * cos($a), $cy + $rr * sin($a));

                if (count($values) === 1) {
                    $parts[] = sprintf('<circle cx="%.2f" cy="%.2f" r="%.2f" fill="%s"/>', $cx, $cy, $r, $colorFn($i));
                } else {
                    $parts[] = sprintf('<path d="M%.2f,%.2f L%s A%.2f,%.2f 0 %d 1 %s Z" fill="%s" stroke="#fff" stroke-width="3"/>',
                        $cx, $cy, $pFn($a0, $r), $r, $r, $large, $pFn($a1, $r), $colorFn($i));
                }

                if ($el->showValues && $v > 0) {
                    $am = ($a0 + $a1) / 2.0;
                    $lr = $chartType === 'doughnut' ? $r * 0.78 : $r * 0.62;
                    $pct = (int)round(($v / $total) * 100.0);
                    $parts[] = sprintf('<text x="%.2f" y="%.2f" text-anchor="middle" font-size="%.2f" font-family="sans-serif" fill="#fff" font-weight="700">%d%%</text>',
                        $cx + $lr * cos($am), $cy + $lr * sin($am) + $fs / 3.0, $fs * 0.9, $pct);
                }

                $a0 = $a1;
            }

            if ($chartType === 'doughnut') {
                $parts[] = sprintf('<circle cx="%.2f" cy="%.2f" r="%.2f" fill="#fff"/>', $cx, $cy, $r * 0.55);
            }

            if ($el->showLegend) {
                self::drawLegend($parts, $labels, $w - $legendW + 10.0, $top + 10.0, $fs, $font, $colorFn);
            }

            return self::wrap($w, $h, $parts);
        }

        // Bar / Column / Line / Area
        $horizontal = ($chartType === 'bar');
        $maxVal = max($values);
        $minVal = min($values);
        $chartMax = max(0.0, (float)$maxVal);
        $chartMin = min(0.0, (float)$minVal);
        $valRange = ($chartMax - $chartMin != 0) ? ($chartMax - $chartMin) : 1.0;

        $maxLabelLen = !empty($labels) ? max(array_map('strlen', $labels)) : 0;
        $labelSpace = $horizontal ? min($w * 0.3, $maxLabelLen * $fs * 0.55 + 10.0) : $fs * 1.8;
        $valueSpace = $horizontal ? $fs * 5.0 : $fs * 5.5;

        $plotX = $horizontal ? $labelSpace : $valueSpace;
        $plotY = $horizontal ? ($top + 6.0) : ($top + $fs);
        $plotW = $horizontal ? ($w - $labelSpace - $fs * 4.0) : ($w - $valueSpace - 10.0);
        $plotH = $horizontal ? ($h - $top - 12.0) : ($h - $top - $fs - $labelSpace);

        $scaleFn = fn($v) => (($v - $chartMin) / $valRange) * ($horizontal ? $plotW : $plotH);

        if ($el->showGrid) {
            for ($i = 0; $i < 5; $i++) {
                $v = $chartMin + ($valRange * $i) / 4.0;
                $vStr = Formatters::formatNumber($v, 0);

                if ($horizontal) {
                    $gx = $plotX + $scaleFn($v);
                    $parts[] = sprintf('<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#e5e7eb" stroke-width="1"/>', $gx, $plotY, $gx, $plotY + $plotH);
                    $parts[] = sprintf('<text x="%.2f" y="%.2f" text-anchor="middle" font-size="%.2f" %s>%s</text>', $gx, $plotY + $plotH + $fs * 1.1, $fs * 0.8, $font, $vStr);
                } else {
                    $gy = $plotY + $plotH - $scaleFn($v);
                    $parts[] = sprintf('<line x1="%.2f" y1="%.2f" x2="%.2f" y2="%.2f" stroke="#e5e7eb" stroke-width="1"/>', $plotX, $gy, $plotX + $plotW, $gy);
                    $parts[] = sprintf('<text x="%.2f" y="%.2f" text-anchor="end" font-size="%.2f" %s>%s</text>', $plotX - 6.0, $gy + $fs * 0.35, $fs * 0.8, $font, $vStr);
                }
            }
        }

        $count = count($values);
        $step = ($horizontal ? $plotH : $plotW) / max(1, $count);
        $barThickness = $step * 0.65;

        for ($i = 0; $i < $count; $i++) {
            $v = (float)$values[$i];
            $lbl = $labels[$i] ?? '';
            $barColor = $colorFn($i);

            if ($horizontal) {
                $by = $plotY + $i * $step + ($step - $barThickness) / 2.0;
                $bw = max(2.0, $scaleFn($v) - $scaleFn(0.0));
                $bx = $plotX + $scaleFn(0.0);

                $parts[] = sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="3" fill="%s"/>', $bx, $by, $bw, $barThickness, $barColor);
                $parts[] = sprintf('<text x="%.2f" y="%.2f" text-anchor="end" font-size="%.2f" %s>%s</text>', $plotX - 6.0, $by + $barThickness / 2.0 + $fs * 0.35, $fs * 0.85, $font, Formatters::escapeHtml($lbl));

                if ($el->showValues) {
                    $parts[] = sprintf('<text x="%.2f" y="%.2f" text-anchor="start" font-size="%.2f" font-weight="600" %s>%s</text>', $bx + $bw + 4.0, $by + $barThickness / 2.0 + $fs * 0.35, $fs * 0.8, $font, Formatters::formatNumber($v, 0));
                }
            } else {
                $bx = $plotX + $i * $step + ($step - $barThickness) / 2.0;
                $bh = max(2.0, $scaleFn($v) - $scaleFn(0.0));
                $by = $plotY + $plotH - $bh;

                $parts[] = sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="3" fill="%s"/>', $bx, $by, $barThickness, $bh, $barColor);
                $parts[] = sprintf('<text x="%.2f" y="%.2f" text-anchor="middle" font-size="%.2f" %s>%s</text>', $bx + $barThickness / 2.0, $plotY + $plotH + $fs * 1.1, $fs * 0.85, $font, Formatters::escapeHtml($lbl));

                if ($el->showValues) {
                    $parts[] = sprintf('<text x="%.2f" y="%.2f" text-anchor="middle" font-size="%.2f" font-weight="600" %s>%s</text>', $bx + $barThickness / 2.0, $by - 4.0, $fs * 0.8, $font, Formatters::formatNumber($v, 0));
                }
            }
        }

        return self::wrap($w, $h, $parts);
    }

    private static function drawLegend(array &$parts, array $labels, float $x, float $y, float $fs, string $font, callable $colorFn): void
    {
        $cy = $y;
        for ($i = 0; $i < count($labels); $i++) {
            $parts[] = sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="2" fill="%s"/>', $x, $cy, $fs * 0.8, $fs * 0.8, $colorFn($i));
            $parts[] = sprintf('<text x="%.2f" y="%.2f" font-size="%.2f" %s>%s</text>', $x + $fs * 1.2, $cy + $fs * 0.7, $fs * 0.8, $font, Formatters::escapeHtml($labels[$i]));
            $cy += $fs * 1.5;
        }
    }

    private static function wrap(float $w, float $h, array $parts): string
    {
        return sprintf('<svg viewBox="0 0 %.2f %.2f" preserveAspectRatio="xMidYMid meet" style="display:block;width:100%;height:100%%" xmlns="http://www.w3.org/2000/svg">%s</svg>', $w, $h, implode('', $parts));
    }
}
