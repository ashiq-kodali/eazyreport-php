<?php

namespace EazyReport\Model;

class LayoutElement
{
    public string $id;
    public string $type = 'text';
    public string $name = '';
    public float $x = 0.0;
    public float $y = 0.0;
    public float $w = 20.0;
    public float $h = 10.0;
    public float $rotate = 0.0;
    public float $opacity = 1.0;

    // Content & Binding
    public string $content = '';
    public string $field = '';
    public string $binding = '';
    public bool $autoHeight = false;
    public string $visibleExpr = '';

    // Styles & Rules
    public TextStyle $style;
    public BoxStyle $box;
    public ValueFormat $format;
    /** @var LogicRule[] */
    public array $rules = [];

    // Barcode specific
    public string $symbology = 'qrcode';
    public string $barcodeValue = '';
    public bool $showText = false;
    public string $barColor = '#000000';
    public string $barcodeBackground = '#ffffff';

    // Chart specific
    public string $chartType = 'column';
    public string $dataset = '';
    public string $labelField = '';
    public string $valueField = '';
    public string $title = '';
    public bool $showLegend = true;
    public bool $showValues = true;
    public bool $showGrid = true;
    public array $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

    // Shape / Line specific
    public string $shapeType = 'rectangle';
    public string $fill = '';
    public string $stroke = '#000000';
    public float $strokeWidth = 1.0;
    public string $strokeDash = '';

    // Image specific
    public string $src = '';
    public string $fit = 'contain';

    // Checkbox specific
    public string $checkedExpr = '';
    public float $boxSize = 4.0;
    public string $checkType = 'check';

    public function __construct()
    {
        $this->id = substr(bin2hex(random_bytes(4)), 0, 8);
        $this->style = new TextStyle();
        $this->box = new BoxStyle();
        $this->format = new ValueFormat();
    }

    public static function fromArray(array $d): self
    {
        $el = new self();
        $el->id = (string)($d['id'] ?? substr(bin2hex(random_bytes(4)), 0, 8));
        $el->type = strtolower((string)($d['type'] ?? 'text'));
        $el->name = (string)($d['name'] ?? '');
        $el->x = (float)($d['x'] ?? 0.0);
        $el->y = (float)($d['y'] ?? 0.0);
        $el->w = (float)($d['w'] ?? $d['width'] ?? 20.0);
        $el->h = (float)($d['h'] ?? $d['height'] ?? 10.0);
        $el->rotate = (float)($d['rotate'] ?? 0.0);
        $el->opacity = (float)($d['opacity'] ?? 1.0);

        $el->content = (string)($d['content'] ?? $d['text'] ?? '');
        $el->field = (string)($d['path'] ?? $d['field'] ?? $d['binding'] ?? '');
        $el->binding = (string)($d['binding'] ?? '');
        $el->autoHeight = (bool)($d['autoHeight'] ?? false);
        $el->visibleExpr = (string)($d['visibleExpr'] ?? '');

        if (isset($d['style']) && is_array($d['style'])) {
            $el->style = TextStyle::fromArray($d['style']);
        }
        if (isset($d['box']) && is_array($d['box'])) {
            $el->box = BoxStyle::fromArray($d['box']);
        }
        $fmtArray = $d['valueFormat'] ?? $d['format'] ?? null;
        if (is_array($fmtArray)) {
            $el->format = ValueFormat::fromArray($fmtArray);
        }

        if (isset($d['rules']) && is_array($d['rules'])) {
            foreach ($d['rules'] as $r) {
                if (is_array($r)) {
                    $el->rules[] = LogicRule::fromArray($r);
                }
            }
        }

        // Barcode
        $el->symbology = strtolower((string)($d['symbology'] ?? 'qrcode'));
        $el->barcodeValue = (string)($d['value'] ?? $d['barcodeValue'] ?? $d['content'] ?? '');
        $el->showText = (bool)($d['showText'] ?? false);
        $el->barColor = (string)($d['barColor'] ?? '#000000');
        $el->barcodeBackground = (string)($d['background'] ?? $d['barcodeBackground'] ?? '#ffffff');

        // Chart
        $el->chartType = strtolower((string)($d['chartType'] ?? 'column'));
        $el->dataset = (string)($d['dataset'] ?? '');
        $el->labelField = (string)($d['labelField'] ?? $d['labelsField'] ?? '');
        $el->valueField = (string)($d['valueField'] ?? $d['valuesField'] ?? '');
        $el->title = (string)($d['title'] ?? '');
        $el->showLegend = (bool)($d['showLegend'] ?? true);
        $el->showValues = (bool)($d['showValues'] ?? true);
        $el->showGrid = (bool)($d['showGrid'] ?? true);
        if (isset($d['colors']) && is_array($d['colors']) && count($d['colors']) > 0) {
            $el->colors = $d['colors'];
        }

        // Shape
        $el->shapeType = strtolower((string)($d['shapeType'] ?? 'rectangle'));
        $el->fill = (string)($d['fill'] ?? '');
        $el->stroke = (string)($d['stroke'] ?? $d['strokeColor'] ?? '#000000');
        $el->strokeWidth = (float)($d['strokeWidth'] ?? 1.0);
        $el->strokeDash = (string)($d['strokeDash'] ?? '');

        // Image
        $el->src = (string)($d['src'] ?? '');
        $el->fit = (string)($d['fit'] ?? 'contain');

        // Checkbox
        $el->checkedExpr = (string)($d['checkedExpr'] ?? '');
        $el->boxSize = (float)($d['boxSize'] ?? 4.0);
        $el->checkType = (string)($d['checkType'] ?? 'check');

        return $el;
    }
}
