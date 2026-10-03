<?php

namespace EazyReport\Engine;

use EazyReport\Expressions\ExpressionEngine;
use EazyReport\Expressions\Formatters;
use EazyReport\Model\Band;
use EazyReport\Model\BoxStyle;
use EazyReport\Model\LayoutElement;
use EazyReport\Model\TextStyle;
use EazyReport\Model\ValueFormat;

class PlacedElementRecord
{
    public LayoutElement $element;
    public float $x;
    public float $y;
    public float $w;
    public float $h;
    public string $text;
    public RenderCtx $context;

    public function __construct(LayoutElement $el, float $x, float $y, float $w, float $h, string $text, RenderCtx $ctx)
    {
        $this->element = $el;
        $this->x = $x;
        $this->y = $y;
        $this->w = $w;
        $this->h = $h;
        $this->text = $text;
        $this->context = $ctx;
    }
}

class PlacedBandRecord
{
    public Band $band;
    public float $x;
    public float $y;
    public float $width;
    public float $height;
    public string $fill;
    /** @var PlacedElementRecord[] */
    public array $elements = [];

    public function __construct(Band $band, float $x, float $y, float $width, float $height, string $fill, array $elements = [])
    {
        $this->band = $band;
        $this->x = $x;
        $this->y = $y;
        $this->width = $width;
        $this->height = $height;
        $this->fill = $fill;
        $this->elements = $elements;
    }
}

class LaidOutBand
{
    public string $html;
    public float $height;
    /** @var PlacedElementRecord[] */
    public array $elements;

    public function __construct(string $html, float $height, array $elements = [])
    {
        $this->html = $html;
        $this->height = $height;
        $this->elements = $elements;
    }
}

class BandLayout
{
    public static function fieldText(LayoutElement $el, RenderCtx $rc): string
    {
        $raw = $el->field !== '' ? $el->field : ($el->binding !== '' ? $el->binding : $el->content);
        if ($raw === '') return '';

        // Check if raw contains Handlebars tags
        if (str_contains($raw, '{{')) {
            $ctxObj = self::buildContextObject($rc);
            return ExpressionEngine::evaluateTemplate($raw, $ctxObj);
        }

        // Direct path or expression evaluation
        $val = $rc->resolveValue($raw);
        if ($val === null && str_contains($raw, ' ')) {
            $val = ExpressionEngine::evaluateExpression($raw, self::buildContextObject($rc));
        }

        if ($val === null) return $raw;

        // Apply formatting
        $fmt = $el->format;
        if ($fmt && $fmt->type !== '' && $fmt->type !== 'text') {
            $fmtType = strtolower($fmt->type);
            if ($fmtType === 'currency') {
                return Formatters::formatCurrency($val, $fmt->currency, $fmt->decimals);
            }
            if ($fmtType === 'number') {
                return Formatters::formatNumber($val, $fmt->decimals, $fmt->thousands);
            }
            if ($fmtType === 'date') {
                $dt = Formatters::toDate($val);
                return $dt ? Formatters::formatDatePattern($dt, $fmt->pattern) : (string)$val;
            }
            if ($fmtType === 'percent') {
                $d = Formatters::toDouble($val);
                return number_format($d, $fmt->decimals, '.', '') . '%';
            }
        }

        return is_scalar($val) ? (string)$val : '';
    }

    public static function measureElementHeight(LayoutElement $el, string $text): float
    {
        if (!$el->autoHeight || $text === '') {
            return $el->h;
        }

        $pt = 0.3528;
        $lineH = $el->style->fontSize * $el->style->lineHeight * $pt;
        $charW = $el->style->fontSize * 0.52 * $pt;
        $padding = $el->box->getPaddingTop() + $el->box->getPaddingBottom();

        $availW = max(1.0, $el->w - $el->box->getPaddingLeft() - $el->box->getPaddingRight());
        $charsPerLine = max(1, (int)($availW / $charW));

        $lines = 0;
        foreach (explode("\n", $text) as $line) {
            $lines += max(1, (int)ceil(strlen($line) / $charsPerLine));
        }

        $neededH = $lines * $lineH + $padding;
        return max($el->h, $neededH);
    }

    public static function layout(Band $band, RenderCtx $rc, float $width, string $fill = '', ?float $fixedHeight = null): ?LaidOutBand
    {
        if ($band->visibleExpr !== '') {
            $ctxObj = self::buildContextObject($rc);
            if (!ExpressionEngine::evaluateCondition($band->visibleExpr, $ctxObj)) {
                return null;
            }
        }

        $placedElements = [];
        $maxBottom = $fixedHeight ?? $band->height;
        $elemHeights = [];

        foreach ($band->elements as $el) {
            $txt = self::fieldText($el, $rc);
            $eh = self::measureElementHeight($el, $txt);
            $elemHeights[$el->id] = $eh;

            if ($band->canGrow && ($el->y + $eh > $maxBottom)) {
                $maxBottom = $el->y + $eh;
            }
        }

        $bandFill = $fill !== '' ? $fill : $band->fill;
        $bgStyle = $bandFill !== '' ? "background-color:{$bandFill};" : '';

        $html = sprintf('<div class="report-band" style="position:relative;width:%.2fmm;height:%.2fmm;%sbox-sizing:border-box;">', $width, $maxBottom, $bgStyle);

        foreach ($band->elements as $el) {
            $elementClone = self::cloneElement($el);
            LogicEvaluator::applyRules($elementClone, $rc);

            if ($elementClone->opacity <= 0.0) continue;

            $txt = self::fieldText($elementClone, $rc);
            $eh = $elemHeights[$el->id] ?? $elementClone->h;

            $placedElements[] = new PlacedElementRecord(
                $elementClone,
                $elementClone->x,
                $elementClone->y,
                $elementClone->w,
                $eh,
                $txt,
                $rc->clone()
            );

            $elHtml = self::renderElementHtml($elementClone, $txt, $eh, $rc);
            $html .= sprintf('<div style="position:absolute;left:%.2fmm;top:%.2fmm;width:%.2fmm;height:%.2fmm;box-sizing:border-box;">%s</div>',
                $elementClone->x, $elementClone->y, $elementClone->w, $eh, $elHtml);
        }

        $html .= '</div>';

        return new LaidOutBand($html, $maxBottom, $placedElements);
    }

    public static function renderElementHtml(LayoutElement $el, string $text, float $actualH, RenderCtx $rc): string
    {
        $type = strtolower($el->type ?: 'text');

        return match ($type) {
            'barcode' => BarcodeGenerator::generateSvg($el->symbology, $el->barcodeValue !== '' ? $el->barcodeValue : $text, $el->barColor, $el->barcodeBackground, $el->showText),
            'chart' => self::renderChartHtml($el, $rc),
            'shape', 'line' => self::renderShapeHtml($el),
            'image' => self::renderImageHtml($el),
            'checkbox' => self::renderCheckboxHtml($el, $rc),
            default => self::renderTextHtml($el, $text, $actualH),
        };
    }

    private static function renderTextHtml(LayoutElement $el, string $text, float $actualH): string
    {
        $s = $el->style;
        $b = $el->box;

        $css = [];
        $escFamily = Formatters::escapeHtml($s->fontFamily);
        $css[] = "font-family:'{$escFamily}', sans-serif";
        $css[] = sprintf('font-size:%.1fpt', $s->fontSize);
        $css[] = "color:{$s->color}";
        $css[] = "text-align:{$s->align}";
        $css[] = sprintf('line-height:%.1f', $s->lineHeight);
        if ($s->bold) $css[] = 'font-weight:700';
        if ($s->italic) $css[] = 'font-style:italic';
        if ($s->underline) $css[] = 'text-decoration:underline';

        if ($b->background !== '') $css[] = "background-color:{$b->background}";
        if ($b->borderWidth > 0 && $b->borderColor !== '') {
            $css[] = sprintf('border:%.1fmm %s %s', $b->borderWidth, $b->borderStyle, $b->borderColor);
        }
        if ($b->borderRadius > 0) $css[] = sprintf('border-radius:%.1fmm', $b->borderRadius);

        $css[] = sprintf('padding:%.1fmm %.1fmm %.1fmm %.1fmm', $b->getPaddingTop(), $b->getPaddingRight(), $b->getPaddingBottom(), $b->getPaddingLeft());
        $css[] = 'box-sizing:border-box;display:flex;width:100%;height:100%;overflow:hidden;word-break:break-word';

        $justify = match ($s->align) {
            'center' => 'center',
            'right' => 'flex-end',
            default => 'flex-start',
        };
        $align = match ($s->verticalAlign) {
            'middle' => 'center',
            'bottom' => 'flex-end',
            default => 'flex-start',
        };
        $css[] = "justify-content:{$justify};align-items:{$align}";

        $escText = nl2br(Formatters::escapeHtml($text));
        $styleAttr = implode(';', $css);

        return "<div style=\"{$styleAttr}\">{$escText}</div>";
    }

    private static function renderShapeHtml(LayoutElement $el): string
    {
        $fill = $el->fill !== '' ? $el->fill : ($el->box->background !== '' ? $el->box->background : 'transparent');
        $stroke = $el->stroke !== '' ? $el->stroke : ($el->box->borderColor !== '' ? $el->box->borderColor : '#000000');
        $strokeW = $el->strokeWidth > 0 ? $el->strokeWidth : ($el->box->borderWidth > 0 ? $el->box->borderWidth : 1.0);

        $shape = strtolower($el->shapeType ?: 'rectangle');
        $svg = sprintf('<svg viewBox="0 0 %.2f %.2f" style="width:100%%;height:100%%;display:block;" xmlns="http://www.w3.org/2000/svg">', $el->w, $el->h);

        if (in_array($shape, ['rectangle', 'rounded_rect'])) {
            $rx = ($shape === 'rounded_rect' || $el->box->borderRadius > 0) ? ($el->box->borderRadius > 0 ? $el->box->borderRadius : 3.0) : 0.0;
            $svg .= sprintf('<rect x="%.2f" y="%.2f" width="%.2f" height="%.2f" rx="%.2f" fill="%s" stroke="%s" stroke-width="%.2f"/>',
                $strokeW / 2, $strokeW / 2, $el->w - $strokeW, $el->h - $strokeW, $rx, $fill, $stroke, $strokeW);
        } elseif (in_array($shape, ['circle', 'ellipse'])) {
            $svg .= sprintf('<ellipse cx="%.2f" cy="%.2f" rx="%.2f" ry="%.2f" fill="%s" stroke="%s" stroke-width="%.2f"/>',
                $el->w / 2, $el->h / 2, ($el->w - $strokeW) / 2, ($el->h - $strokeW) / 2, $fill, $stroke, $strokeW);
        } elseif ($shape === 'line') {
            $svg .= sprintf('<line x1="0" y1="%.2f" x2="%.2f" y2="%.2f" stroke="%s" stroke-width="%.2f"/>',
                $el->h / 2, $el->w, $el->h / 2, $stroke, $strokeW);
        }

        $svg .= '</svg>';
        return $svg;
    }

    private static function renderImageHtml(LayoutElement $el): string
    {
        if ($el->src === '') return '';
        $fit = match ($el->fit) {
            'cover' => 'object-fit:cover;',
            'fill' => 'object-fit:fill;',
            default => 'object-fit:contain;',
        };
        return sprintf('<img src="%s" style="width:100%%;height:100%%;%sdisplay:block;" />', htmlspecialchars($el->src, ENT_QUOTES), $fit);
    }

    private static function renderCheckboxHtml(LayoutElement $el, RenderCtx $rc): string
    {
        $isChecked = false;
        if ($el->checkedExpr !== '') {
            $ctxObj = self::buildContextObject($rc);
            $isChecked = ExpressionEngine::evaluateCondition($el->checkedExpr, $ctxObj);
        }

        $checkChar = $isChecked ? '✓' : '';
        $border = $el->box->borderWidth > 0 ? sprintf('%.1fmm solid %s', $el->box->borderWidth, $el->box->borderColor) : '1.5px solid #000';
        $bg = $el->box->background !== '' ? $el->box->background : '#ffffff';

        return "<div style=\"width:100%;height:100%;display:flex;align-items:center;justify-content:center;border:{$border};background-color:{$bg};font-weight:bold;font-size:10pt;\">{$checkChar}</div>";
    }

    private static function renderChartHtml(LayoutElement $el, RenderCtx $rc): string
    {
        $labels = [];
        $values = [];

        $list = $el->dataset !== '' ? $rc->resolveValue($el->dataset) : $rc->data;
        if (is_iterable($list)) {
            foreach ($list as $item) {
                $lVal = $el->labelField !== '' ? ExpressionEngine::getPath($item, $el->labelField) : null;
                $vVal = $el->valueField !== '' ? ExpressionEngine::getPath($item, $el->valueField) : null;

                $labels[] = (string)($lVal ?? '');
                $values[] = Formatters::toDouble($vVal);
            }
        }

        return ChartGenerator::generateSvg($el, $labels, $values);
    }

    public static function buildContextObject(RenderCtx $rc): array
    {
        $dict = [
            'Page' => $rc->pageNumber,
            'TotalPages' => $rc->totalPages,
            'pageNumber' => $rc->pageNumber,
            'totalPages' => $rc->totalPages,
        ];

        if ($rc->data !== null) {
            $dict['data'] = $rc->data;
            $dict['root'] = $rc->data;
            if (is_array($rc->data)) {
                foreach ($rc->data as $k => $v) {
                    $dict[$k] = $v;
                }
            }
        }

        if ($rc->item !== null) {
            $dict['item'] = $rc->item;
            $dict['this'] = $rc->item;
            if (is_array($rc->item)) {
                foreach ($rc->item as $k => $v) {
                    $dict[$k] = $v;
                }
            }
        }

        foreach ($rc->params as $k => $v) {
            $dict[$k] = $v;
        }

        return $dict;
    }

    private static function cloneElement(LayoutElement $src): LayoutElement
    {
        $el = clone $src;
        $el->style = clone $src->style;
        $el->box = clone $src->box;
        $el->format = clone $src->format;
        $el->rules = array_map(fn($r) => clone $r, $src->rules);
        return $el;
    }
}
