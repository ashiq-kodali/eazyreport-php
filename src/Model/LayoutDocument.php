<?php

namespace EazyReport\Model;

class LayoutPage
{
    public string $id;
    public string $name = 'Page1';
    public string $size = 'A4';
    public string $orientation = 'portrait';
    public float $width = 210.0;
    public float $height = 297.0;
    public PageMargins $margins;
    public float $printOffsetX = 0.0;
    public float $printOffsetY = 0.0;
    public Watermark $watermark;

    public ?Band $reportTitle = null;
    public ?Band $reportSummary = null;
    public ?Band $pageHeader = null;
    public ?Band $pageFooter = null;
    public ?Band $columnHeader = null;
    public ?Band $columnFooter = null;
    public ?Band $header = null;
    public ?Band $footer = null;
    public ?Band $overlay = null;

    /** @var DataBand[] */
    public array $dataBands = [];

    public function __construct()
    {
        $this->id = substr(bin2hex(random_bytes(4)), 0, 8);
        $this->margins = new PageMargins();
        $this->watermark = new Watermark();
    }

    public static function fromArray(array $d): self
    {
        $p = new self();
        $p->id = (string)($d['id'] ?? substr(bin2hex(random_bytes(4)), 0, 8));
        $p->name = (string)($d['name'] ?? 'Page1');
        $p->size = (string)($d['size'] ?? 'A4');
        $p->orientation = strtolower((string)($d['orientation'] ?? 'portrait'));

        $isPortrait = ($p->orientation === 'portrait');
        $p->width = (float)($d['width'] ?? ($isPortrait ? 210.0 : 297.0));
        $p->height = (float)($d['height'] ?? ($isPortrait ? 297.0 : 210.0));
        $p->printOffsetX = (float)($d['printOffsetX'] ?? 0.0);
        $p->printOffsetY = (float)($d['printOffsetY'] ?? 0.0);

        if (isset($d['margins']) && is_array($d['margins'])) {
            $p->margins = PageMargins::fromArray($d['margins']);
        }
        if (isset($d['watermark']) && is_array($d['watermark'])) {
            $p->watermark = Watermark::fromArray($d['watermark']);
        }

        // Standard Bands
        if (isset($d['reportTitle']) && is_array($d['reportTitle'])) {
            $p->reportTitle = Band::fromArray($d['reportTitle'], 'reportTitle');
        }
        if (isset($d['reportSummary']) && is_array($d['reportSummary'])) {
            $p->reportSummary = Band::fromArray($d['reportSummary'], 'reportSummary');
        }
        if (isset($d['pageHeader']) && is_array($d['pageHeader'])) {
            $p->pageHeader = Band::fromArray($d['pageHeader'], 'pageHeader');
        }
        if (isset($d['pageFooter']) && is_array($d['pageFooter'])) {
            $p->pageFooter = Band::fromArray($d['pageFooter'], 'pageFooter');
        }
        if (isset($d['columnHeader']) && is_array($d['columnHeader'])) {
            $p->columnHeader = Band::fromArray($d['columnHeader'], 'columnHeader');
        }
        if (isset($d['columnFooter']) && is_array($d['columnFooter'])) {
            $p->columnFooter = Band::fromArray($d['columnFooter'], 'columnFooter');
        }
        if (isset($d['overlay']) && is_array($d['overlay'])) {
            $p->overlay = Band::fromArray($d['overlay'], 'overlay');
        }

        // DataBands (supports dataBands or data)
        $rawDb = $d['dataBands'] ?? $d['data'] ?? [];
        if (is_array($rawDb)) {
            // Check if associative or indexed array
            if (array_keys($rawDb) !== range(0, count($rawDb) - 1) && !empty($rawDb)) {
                $db = DataBand::fromDataArray($rawDb);
                if ($db) $p->dataBands[] = $db;
            } else {
                foreach ($rawDb as $dbItem) {
                    if (is_array($dbItem)) {
                        $db = DataBand::fromDataArray($dbItem);
                        if ($db) $p->dataBands[] = $db;
                    }
                }
            }
        }

        return $p;
    }
}

class LayoutDocument
{
    public string $format = 'report-designer-layout';
    public int $version = 2;
    public string $title = 'Report';
    public string $author = '';
    public string $description = '';
    /** @var LayoutPage[] */
    public array $pages = [];
    public array $datasets = [];
    public array $parameters = [];
    public array $styles = [];

    public static function fromArray(array $d): self
    {
        $doc = new self();
        $doc->format = (string)($d['format'] ?? 'report-designer-layout');
        $doc->version = (int)($d['version'] ?? 2);
        $doc->title = (string)($d['title'] ?? 'Report');
        $doc->author = (string)($d['author'] ?? '');
        $doc->description = (string)($d['description'] ?? '');

        if (isset($d['pages']) && is_array($d['pages'])) {
            foreach ($d['pages'] as $pageData) {
                if (is_array($pageData)) {
                    $doc->pages[] = LayoutPage::fromArray($pageData);
                }
            }
        }

        if (empty($doc->pages)) {
            $doc->pages[] = new LayoutPage();
        }

        if (isset($d['parameters']) && is_array($d['parameters'])) {
            $doc->parameters = $d['parameters'];
        }
        if (isset($d['datasets']) && is_array($d['datasets'])) {
            $doc->datasets = $d['datasets'];
        }
        if (isset($d['styles']) && is_array($d['styles'])) {
            $doc->styles = $d['styles'];
        }

        return $doc;
    }
}
