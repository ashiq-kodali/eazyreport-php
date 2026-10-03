<?php

namespace EazyReport\Engine;

use EazyReport\Expressions\ExpressionEngine;
use EazyReport\Expressions\Formatters;
use EazyReport\Model\LayoutElement;

class RenderCtx
{
    public mixed $data = null;
    public mixed $item = null;
    public array $params = [];
    public int $pageNumber = 1;
    public int $totalPages = 1;

    public function clone(): self
    {
        $rc = new self();
        $rc->data = $this->data;
        $rc->item = $this->item;
        $rc->params = $this->params;
        $rc->pageNumber = $this->pageNumber;
        $rc->totalPages = $this->totalPages;
        return $rc;
    }

    public function resolveValue(string $path): mixed
    {
        if (trim($path) === '') return null;

        if (strcasecmp($path, 'Page') === 0 || strcasecmp($path, 'pageNumber') === 0) return $this->pageNumber;
        if (strcasecmp($path, 'TotalPages') === 0 || strcasecmp($path, 'totalPages') === 0) return $this->totalPages;

        if (str_starts_with(strtolower($path), 'params.')) {
            $pKey = substr($path, 7);
            return $this->params[$pKey] ?? null;
        }

        // Try item context first, then root data, then params
        if ($this->item !== null) {
            $v = ExpressionEngine::getPath($this->item, $path);
            if ($v !== null) return $v;
        }

        if ($this->data !== null) {
            $v = ExpressionEngine::getPath($this->data, $path);
            if ($v !== null) return $v;
        }

        if (array_key_exists($path, $this->params)) {
            return $this->params[$path];
        }

        return null;
    }
}

class LogicEvaluator
{
    public static function applyRules(LayoutElement $el, RenderCtx $rc): void
    {
        if (empty($el->rules)) return;

        $ctxObj = BandLayout::buildContextObject($rc);

        foreach ($el->rules as $rule) {
            if (!$rule->enabled || trim($rule->condition) === '') continue;

            $matches = ExpressionEngine::evaluateCondition($rule->condition, $ctxObj);
            if (!$matches) continue;

            $prop = strtolower(trim($rule->property));
            $valStr = (string)$rule->value;

            switch ($prop) {
                case 'color':
                case 'fontcolor':
                case 'textcolor':
                    $el->style->color = $valStr;
                    break;
                case 'background':
                case 'backgroundcolor':
                case 'fill':
                    $el->box->background = $valStr;
                    break;
                case 'bold':
                case 'fontweight':
                    $el->style->bold = ExpressionEngine::isTruthy($rule->value);
                    break;
                case 'italic':
                    $el->style->italic = ExpressionEngine::isTruthy($rule->value);
                    break;
                case 'bordercolor':
                    $el->box->borderColor = $valStr;
                    break;
                case 'borderwidth':
                    $el->box->borderWidth = Formatters::toDouble($rule->value);
                    break;
                case 'visible':
                    if (!ExpressionEngine::isTruthy($rule->value)) {
                        $el->opacity = 0.0;
                    }
                    break;
            }
        }
    }
}
