<?php

namespace EazyReport\Model;

class TextStyle
{
    public string $fontFamily = 'Inter';
    public float $fontSize = 10.0;
    public bool $bold = false;
    public bool $italic = false;
    public bool $underline = false;
    public string $color = '#000000';
    public string $align = 'left';
    public string $verticalAlign = 'top';
    public float $lineHeight = 1.2;

    public static function fromArray(?array $d): self
    {
        $s = new self();
        if (!$d) return $s;
        $s->fontFamily = (string)($d['fontFamily'] ?? 'Inter');
        $s->fontSize = (float)($d['fontSize'] ?? 10.0);
        $s->bold = (bool)($d['bold'] ?? false);
        $s->italic = (bool)($d['italic'] ?? false);
        $s->underline = (bool)($d['underline'] ?? false);
        $s->color = (string)($d['color'] ?? '#000000');
        $s->align = (string)($d['align'] ?? 'left');
        $s->verticalAlign = (string)($d['verticalAlign'] ?? $d['vAlign'] ?? 'top');
        $s->lineHeight = (float)($d['lineHeight'] ?? 1.2);
        return $s;
    }
}

class BoxStyle
{
    public string $background = '';
    public float $borderWidth = 0.0;
    public string $borderColor = '#000000';
    public string $borderStyle = 'solid';
    public float $borderRadius = 0.0;
    public float $padding = 0.0;
    public ?float $paddingTop = null;
    public ?float $paddingRight = null;
    public ?float $paddingBottom = null;
    public ?float $paddingLeft = null;

    public function getPaddingTop(): float { return $this->paddingTop ?? $this->padding; }
    public function getPaddingRight(): float { return $this->paddingRight ?? $this->padding; }
    public function getPaddingBottom(): float { return $this->paddingBottom ?? $this->padding; }
    public function getPaddingLeft(): float { return $this->paddingLeft ?? $this->padding; }

    public static function fromArray(?array $d): self
    {
        $b = new self();
        if (!$d) return $b;
        $b->background = (string)($d['background'] ?? '');
        $b->borderWidth = (float)($d['borderWidth'] ?? 0.0);
        $b->borderColor = (string)($d['borderColor'] ?? '#000000');
        $b->borderStyle = (string)($d['borderStyle'] ?? 'solid');
        $b->borderRadius = (float)($d['borderRadius'] ?? 0.0);
        $b->padding = (float)($d['padding'] ?? 0.0);
        if (isset($d['paddingTop'])) $b->paddingTop = (float)$d['paddingTop'];
        if (isset($d['paddingRight'])) $b->paddingRight = (float)$d['paddingRight'];
        if (isset($d['paddingBottom'])) $b->paddingBottom = (float)$d['paddingBottom'];
        if (isset($d['paddingLeft'])) $b->paddingLeft = (float)$d['paddingLeft'];
        return $b;
    }
}

class ValueFormat
{
    public string $type = 'text';
    public string $pattern = '';
    public string $currency = 'USD';
    public int $decimals = 2;
    public bool $thousands = true;

    public static function fromArray(?array $d): self
    {
        $f = new self();
        if (!$d) return $f;
        $f->type = (string)($d['format'] ?? $d['type'] ?? 'text');
        $f->pattern = (string)($d['datePattern'] ?? $d['pattern'] ?? '');
        $f->currency = (string)($d['currency'] ?? 'USD');
        $f->decimals = (int)($d['decimals'] ?? 2);
        $f->thousands = (bool)($d['thousands'] ?? true);
        return $f;
    }
}

class PageMargins
{
    public float $top = 10.0;
    public float $right = 10.0;
    public float $bottom = 10.0;
    public float $left = 10.0;

    public static function fromArray(?array $d): self
    {
        $m = new self();
        if (!$d) return $m;
        $m->top = (float)($d['top'] ?? 10.0);
        $m->right = (float)($d['right'] ?? 10.0);
        $m->bottom = (float)($d['bottom'] ?? 10.0);
        $m->left = (float)($d['left'] ?? 10.0);
        return $m;
    }
}

class Watermark
{
    public bool $enabled = false;
    public string $text = 'DRAFT';
    public string $color = '#94a3b8';
    public float $fontSize = 90.0;
    public float $angle = -45.0;
    public float $opacity = 0.18;
    public string $image = '';
    public bool $onTop = false;

    public static function fromArray(?array $d): self
    {
        $w = new self();
        if (!$d) return $w;
        $w->enabled = (bool)($d['enabled'] ?? false);
        $w->text = (string)($d['text'] ?? 'DRAFT');
        $w->color = (string)($d['color'] ?? '#94a3b8');
        $w->fontSize = (float)($d['fontSize'] ?? 90.0);
        $w->angle = (float)($d['angle'] ?? -45.0);
        $w->opacity = (float)($d['opacity'] ?? 0.18);
        $w->image = (string)($d['image'] ?? '');
        $w->onTop = (bool)($d['onTop'] ?? false);
        return $w;
    }
}

class LogicRule
{
    public string $condition = '';
    public string $property = '';
    public mixed $value = null;
    public bool $enabled = true;

    public static function fromArray(?array $d): self
    {
        $r = new self();
        if (!$d) return $r;
        $r->condition = (string)($d['condition'] ?? '');
        $r->property = (string)($d['property'] ?? '');
        $r->value = $d['value'] ?? null;
        $r->enabled = (bool)($d['enabled'] ?? true);
        return $r;
    }
}
