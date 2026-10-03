<?php

namespace EazyReport\Model;

class Band
{
    public string $id;
    public string $type = 'band';
    public string $name = '';
    public float $height = 20.0;
    public bool $canGrow = false;
    public bool $canShrink = false;
    public string $visibleExpr = '';
    public string $fill = '';
    public bool $startNewPage = false;
    public bool $printOnFirstPage = true;
    public bool $printOnLastPage = true;
    public bool $repeatOnEveryPage = false;
    /** @var LayoutElement[] */
    public array $elements = [];

    public function __construct()
    {
        $this->id = substr(bin2hex(random_bytes(4)), 0, 8);
    }

    public static function fromArray(?array $d, string $defaultType = 'band'): ?self
    {
        if (!$d) return null;
        $b = new self();
        $b->id = (string)($d['id'] ?? substr(bin2hex(random_bytes(4)), 0, 8));
        $b->type = (string)($d['type'] ?? $defaultType);
        $b->name = (string)($d['name'] ?? $defaultType);
        $b->height = (float)($d['height'] ?? 20.0);
        $b->canGrow = (bool)($d['canGrow'] ?? false);
        $b->canShrink = (bool)($d['canShrink'] ?? false);
        $b->visibleExpr = (string)($d['visibleExpr'] ?? '');
        $b->fill = (string)($d['fill'] ?? '');
        $b->startNewPage = (bool)($d['startNewPage'] ?? false);
        $b->printOnFirstPage = (bool)($d['printOnFirstPage'] ?? true);
        $b->printOnLastPage = (bool)($d['printOnLastPage'] ?? true);
        $b->repeatOnEveryPage = (bool)($d['repeatOnEveryPage'] ?? false);

        if (isset($d['elements']) && is_array($d['elements'])) {
            foreach ($d['elements'] as $elData) {
                if (is_array($elData)) {
                    $b->elements[] = LayoutElement::fromArray($elData);
                }
            }
        }
        return $b;
    }
}

class GroupBand extends Band
{
    public string $expression = '';
    public bool $resetPageNumber = false;
    public bool $keepTogether = false;

    public static function fromGroupArray(?array $d): ?self
    {
        if (!$d) return null;
        $gb = new self();
        $b = parent::fromArray($d, 'groupHeader');
        if (!$b) return null;

        $gb->id = $b->id;
        $gb->type = $b->type;
        $gb->name = $b->name;
        $gb->height = $b->height;
        $gb->canGrow = $b->canGrow;
        $gb->canShrink = $b->canShrink;
        $gb->visibleExpr = $b->visibleExpr;
        $gb->fill = $b->fill;
        $gb->startNewPage = $b->startNewPage;
        $gb->printOnFirstPage = $b->printOnFirstPage;
        $gb->printOnLastPage = $b->printOnLastPage;
        $gb->repeatOnEveryPage = $b->repeatOnEveryPage;
        $gb->elements = $b->elements;

        $gb->expression = (string)($d['expression'] ?? '');
        $gb->resetPageNumber = (bool)($d['resetPageNumber'] ?? false);
        $gb->keepTogether = (bool)($d['keepTogether'] ?? false);

        return $gb;
    }
}

class ChildBand extends Band
{
    public bool $keepWithParent = false;
    public bool $fillUnusedSpace = false;

    public static function fromChildArray(?array $d): ?self
    {
        if (!$d) return null;
        $cb = new self();
        $b = parent::fromArray($d, 'child');
        if (!$b) return null;

        $cb->id = $b->id;
        $cb->type = $b->type;
        $cb->name = $b->name;
        $cb->height = $b->height;
        $cb->canGrow = $b->canGrow;
        $cb->canShrink = $b->canShrink;
        $cb->visibleExpr = $b->visibleExpr;
        $cb->fill = $b->fill;
        $cb->startNewPage = $b->startNewPage;
        $cb->printOnFirstPage = $b->printOnFirstPage;
        $cb->printOnLastPage = $b->printOnLastPage;
        $cb->repeatOnEveryPage = $b->repeatOnEveryPage;
        $cb->elements = $b->elements;

        $cb->keepWithParent = (bool)($d['keepWithParent'] ?? false);
        $cb->fillUnusedSpace = (bool)($d['fillUnusedSpace'] ?? false);

        return $cb;
    }
}

class DataBand extends Band
{
    public string $dataset = '';
    public string $dataPath = '';
    public string $alias = 'item';
    public string $filterExpr = '';
    public string $sortField = '';
    public string $sortDir = 'asc';
    public bool $keepWithNext = false;
    public bool $keepTogether = false;
    public bool $fillUnusedSpace = false;
    public int $columnCount = 1;
    public float $columnSpacing = 0.0;
    public float $columnWidth = 0.0;

    public ?Band $header = null;
    public ?Band $footer = null;
    /** @var GroupBand[] */
    public array $groupHeaders = [];
    /** @var GroupBand[] */
    public array $groupFooters = [];
    /** @var ChildBand[] */
    public array $childBands = [];

    public static function fromDataArray(?array $d): ?self
    {
        if (!$d) return null;
        $db = new self();
        $b = parent::fromArray($d, 'data');
        if (!$b) return null;

        $db->id = $b->id;
        $db->type = $b->type;
        $db->name = $b->name;
        $db->height = $b->height;
        $db->canGrow = $b->canGrow;
        $db->canShrink = $b->canShrink;
        $db->visibleExpr = $b->visibleExpr;
        $db->fill = $b->fill;
        $db->startNewPage = $b->startNewPage;
        $db->printOnFirstPage = $b->printOnFirstPage;
        $db->printOnLastPage = $b->printOnLastPage;
        $db->repeatOnEveryPage = $b->repeatOnEveryPage;
        $db->elements = $b->elements;

        $dp = (string)($d['dataPath'] ?? $d['dataset'] ?? $d['arrayPath'] ?? '');
        $db->dataset = $dp;
        $db->dataPath = $dp;
        $db->alias = (string)($d['alias'] ?? 'item');
        $db->filterExpr = (string)($d['filterExpr'] ?? '');
        $db->sortField = (string)($d['sortField'] ?? '');
        $db->sortDir = (string)($d['sortDir'] ?? 'asc');
        $db->keepWithNext = (bool)($d['keepWithNext'] ?? false);
        $db->keepTogether = (bool)($d['keepTogether'] ?? false);
        $db->fillUnusedSpace = (bool)($d['fillUnusedSpace'] ?? false);
        $db->columnCount = (int)($d['columnCount'] ?? 1);
        $db->columnSpacing = (float)($d['columnSpacing'] ?? 0.0);
        $db->columnWidth = (float)($d['columnWidth'] ?? 0.0);

        if (isset($d['header']) && is_array($d['header'])) {
            $db->header = Band::fromArray($d['header'], 'dataHeader');
        }
        if (isset($d['footer']) && is_array($d['footer'])) {
            $db->footer = Band::fromArray($d['footer'], 'dataFooter');
        }

        if (isset($d['groupHeaders']) && is_array($d['groupHeaders'])) {
            foreach ($d['groupHeaders'] as $g) {
                if (is_array($g)) {
                    $gb = GroupBand::fromGroupArray($g);
                    if ($gb) $db->groupHeaders[] = $gb;
                }
            }
        }

        if (isset($d['groupFooters']) && is_array($d['groupFooters'])) {
            foreach ($d['groupFooters'] as $g) {
                if (is_array($g)) {
                    $gb = GroupBand::fromGroupArray($g);
                    if ($gb) $db->groupFooters[] = $gb;
                }
            }
        }

        $rawChildren = $d['childBands'] ?? $d['children'] ?? [];
        if (is_array($rawChildren)) {
            foreach ($rawChildren as $c) {
                if (is_array($c)) {
                    $cb = ChildBand::fromChildArray($c);
                    if ($cb) $db->childBands[] = $cb;
                }
            }
        }

        return $db;
    }
}
