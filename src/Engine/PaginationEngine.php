<?php

namespace EazyReport\Engine;

use EazyReport\Expressions\ExpressionEngine;
use EazyReport\Model\DataBand;
use EazyReport\Model\LayoutDocument;
use EazyReport\Model\PageMargins;
use EazyReport\Model\Watermark;

class RenderedPage
{
    public string $id;
    public float $width = 210.0;
    public float $height = 297.0;
    public string $html = '';
    /** @var PlacedBandRecord[] */
    public array $placedBands = [];
    public Watermark $watermark;
    public PageMargins $margins;

    public function __construct(string $id, float $width, float $height, string $html, array $placedBands, Watermark $wm, PageMargins $m)
    {
        $this->id = $id;
        $this->width = $width;
        $this->height = $height;
        $this->html = $html;
        $this->placedBands = $placedBands;
        $this->watermark = $wm;
        $this->margins = $m;
    }
}

class PaginationEngine
{
    /**
     * @return RenderedPage[]
     */
    public static function paginate(LayoutDocument $doc, mixed $data, array $parameters = [], ?array $options = null): array
    {
        $allParams = array_merge($doc->parameters ?? [], $parameters);
        if (isset($options['params']) && is_array($options['params'])) {
            $allParams = array_merge($allParams, $options['params']);
        }
        $maxPages = (int)($options['maxPages'] ?? 2000);

        // Pass 1: compute page count
        $pass1 = self::executePaginationPass($doc, $data, $allParams, 1, $maxPages);
        $totalPages = max(1, count($pass1));

        // Pass 2: layout with accurate total pages
        return self::executePaginationPass($doc, $data, $allParams, $totalPages, $maxPages);
    }

    /**
     * @return RenderedPage[]
     */
    private static function executePaginationPass(LayoutDocument $doc, mixed $data, array $parameters, int $totalPages, int $maxPages): array
    {
        $pages = [];
        if (empty($doc->pages)) return $pages;

        $pageTpl = $doc->pages[0];
        $pw = $pageTpl->width;
        $ph = $pageTpl->height;
        $margins = $pageTpl->margins;
        $bodyW = $pw - $margins->left - $margins->right;
        $bodyH = $ph - $margins->top - $margins->bottom;

        $footerH = $pageTpl->pageFooter !== null ? $pageTpl->pageFooter->height : 0.0;
        $maxBodyY = $margins->top + $bodyH - $footerH;

        $pageNum = 1;
        $currentY = $margins->top;
        $currentPlacedBands = [];
        $currentBandHtml = '';

        $flushPage = function () use (&$pages, &$currentPlacedBands, &$currentBandHtml, &$pageNum, &$currentY, $pageTpl, $pw, $ph, $margins, $bodyW, $data, $parameters, $totalPages) {
            // Render PageFooter at bottom
            if ($pageTpl->pageFooter !== null) {
                $footerRc = new RenderCtx();
                $footerRc->data = $data;
                $footerRc->params = $parameters;
                $footerRc->pageNumber = $pageNum;
                $footerRc->totalPages = $totalPages;

                $fy = $ph - $margins->bottom - $pageTpl->pageFooter->height;
                $laidFooter = BandLayout::layout($pageTpl->pageFooter, $footerRc, $bodyW);
                if ($laidFooter !== null) {
                    $currentPlacedBands[] = new PlacedBandRecord(
                        $pageTpl->pageFooter,
                        $margins->left,
                        $fy,
                        $bodyW,
                        $laidFooter->height,
                        $pageTpl->pageFooter->fill,
                        $laidFooter->elements
                    );
                    $currentBandHtml .= sprintf('<div style="position:absolute;left:%.2fmm;top:%.2fmm;width:%.2fmm;height:%.2fmm;">%s</div>',
                        $margins->left, $fy, $bodyW, $laidFooter->height, $laidFooter->html);
                }
            }

            $pages[] = new RenderedPage(
                "page-{$pageNum}",
                $pw,
                $ph,
                $currentBandHtml,
                $currentPlacedBands,
                $pageTpl->watermark,
                $margins
            );

            $currentPlacedBands = [];
            $currentBandHtml = '';
            $pageNum++;
            $currentY = $margins->top;
        };

        $startNewPage = function () use (&$flushPage, &$currentY, &$currentPlacedBands, &$currentBandHtml, $pageTpl, $margins, $bodyW, $data, $parameters, $pageNum, $totalPages) {
            $flushPage();

            // Render PageHeader on new page
            if ($pageTpl->pageHeader !== null) {
                $headerRc = new RenderCtx();
                $headerRc->data = $data;
                $headerRc->params = $parameters;
                $headerRc->pageNumber = $pageNum;
                $headerRc->totalPages = $totalPages;

                $laidHeader = BandLayout::layout($pageTpl->pageHeader, $headerRc, $bodyW);
                if ($laidHeader !== null) {
                    $currentPlacedBands[] = new PlacedBandRecord(
                        $pageTpl->pageHeader,
                        $margins->left,
                        $currentY,
                        $bodyW,
                        $laidHeader->height,
                        $pageTpl->pageHeader->fill,
                        $laidHeader->elements
                    );
                    $currentBandHtml .= sprintf('<div style="position:absolute;left:%.2fmm;top:%.2fmm;width:%.2fmm;height:%.2fmm;">%s</div>',
                        $margins->left, $currentY, $bodyW, $laidHeader->height, $laidHeader->html);
                    $currentY += $laidHeader->height;
                }
            }
        };

        // 1. ReportTitle (on first page)
        if ($pageTpl->reportTitle !== null && $pageTpl->reportTitle->printOnFirstPage) {
            $titleRc = new RenderCtx();
            $titleRc->data = $data;
            $titleRc->params = $parameters;
            $titleRc->pageNumber = $pageNum;
            $titleRc->totalPages = $totalPages;

            $laidTitle = BandLayout::layout($pageTpl->reportTitle, $titleRc, $bodyW);
            if ($laidTitle !== null) {
                $currentPlacedBands[] = new PlacedBandRecord(
                    $pageTpl->reportTitle,
                    $margins->left,
                    $currentY,
                    $bodyW,
                    $laidTitle->height,
                    $pageTpl->reportTitle->fill,
                    $laidTitle->elements
                );
                $currentBandHtml .= sprintf('<div style="position:absolute;left:%.2fmm;top:%.2fmm;width:%.2fmm;height:%.2fmm;">%s</div>',
                    $margins->left, $currentY, $bodyW, $laidTitle->height, $laidTitle->html);
                $currentY += $laidTitle->height;
            }
        }

        // 2. PageHeader (if first page and printOnFirstPage)
        if ($pageTpl->pageHeader !== null && $pageTpl->pageHeader->printOnFirstPage) {
            $headerRc = new RenderCtx();
            $headerRc->data = $data;
            $headerRc->params = $parameters;
            $headerRc->pageNumber = $pageNum;
            $headerRc->totalPages = $totalPages;

            $laidHeader = BandLayout::layout($pageTpl->pageHeader, $headerRc, $bodyW);
            if ($laidHeader !== null) {
                $currentPlacedBands[] = new PlacedBandRecord(
                    $pageTpl->pageHeader,
                    $margins->left,
                    $currentY,
                    $bodyW,
                    $laidHeader->height,
                    $pageTpl->pageHeader->fill,
                    $laidHeader->elements
                );
                $currentBandHtml .= sprintf('<div style="position:absolute;left:%.2fmm;top:%.2fmm;width:%.2fmm;height:%.2fmm;">%s</div>',
                    $margins->left, $currentY, $bodyW, $laidHeader->height, $laidHeader->html);
                $currentY += $laidHeader->height;
            }
        }

        // 3. Process DataBands
        foreach ($pageTpl->dataBands as $dataBand) {
            $records = self::resolveRecords($dataBand, $data);

            // DataHeader
            if ($dataBand->header !== null) {
                $dhRc = new RenderCtx();
                $dhRc->data = $data;
                $dhRc->params = $parameters;
                $dhRc->pageNumber = $pageNum;
                $dhRc->totalPages = $totalPages;

                $laidDh = BandLayout::layout($dataBand->header, $dhRc, $bodyW);
                if ($laidDh !== null) {
                    if ($currentY + $laidDh->height > $maxBodyY) {
                        $startNewPage();
                    }

                    $currentPlacedBands[] = new PlacedBandRecord(
                        $dataBand->header,
                        $margins->left,
                        $currentY,
                        $bodyW,
                        $laidDh->height,
                        $dataBand->header->fill,
                        $laidDh->elements
                    );
                    $currentBandHtml .= sprintf('<div style="position:absolute;left:%.2fmm;top:%.2fmm;width:%.2fmm;height:%.2fmm;">%s</div>',
                        $margins->left, $currentY, $bodyW, $laidDh->height, $laidDh->html);
                    $currentY += $laidDh->height;
                }
            }

            // Records
            foreach ($records as $item) {
                $itemRc = new RenderCtx();
                $itemRc->data = $data;
                $itemRc->item = $item;
                $itemRc->params = $parameters;
                $itemRc->pageNumber = $pageNum;
                $itemRc->totalPages = $totalPages;

                $laidItem = BandLayout::layout($dataBand, $itemRc, $bodyW);
                if ($laidItem !== null) {
                    if ($currentY + $laidItem->height > $maxBodyY) {
                        $startNewPage();
                    }

                    $currentPlacedBands[] = new PlacedBandRecord(
                        $dataBand,
                        $margins->left,
                        $currentY,
                        $bodyW,
                        $laidItem->height,
                        $dataBand->fill,
                        $laidItem->elements
                    );
                    $currentBandHtml .= sprintf('<div style="position:absolute;left:%.2fmm;top:%.2fmm;width:%.2fmm;height:%.2fmm;">%s</div>',
                        $margins->left, $currentY, $bodyW, $laidItem->height, $laidItem->html);
                    $currentY += $laidItem->height;
                }

                // ChildBands (non-filling)
                foreach ($dataBand->childBands as $cb) {
                    if ($cb->fillUnusedSpace) continue;

                    $laidCb = BandLayout::layout($cb, $itemRc, $bodyW);
                    if ($laidCb !== null) {
                        if ($currentY + $laidCb->height > $maxBodyY) {
                            $startNewPage();
                        }

                        $currentPlacedBands[] = new PlacedBandRecord(
                            $cb,
                            $margins->left,
                            $currentY,
                            $bodyW,
                            $laidCb->height,
                            $cb->fill,
                            $laidCb->elements
                        );
                        $currentBandHtml .= sprintf('<div style="position:absolute;left:%.2fmm;top:%.2fmm;width:%.2fmm;height:%.2fmm;">%s</div>',
                            $margins->left, $currentY, $bodyW, $laidCb->height, $laidCb->html);
                        $currentY += $laidCb->height;
                    }
                }
            }

            // FillUnusedSpace ChildBands
            foreach ($dataBand->childBands as $cb) {
                if (!$cb->fillUnusedSpace || $cb->height <= 0) continue;

                $blankRc = new RenderCtx();
                $blankRc->data = $data;
                $blankRc->params = $parameters;
                $blankRc->pageNumber = $pageNum;
                $blankRc->totalPages = $totalPages;

                while ($currentY + $cb->height <= $maxBodyY) {
                    $laidFill = BandLayout::layout($cb, $blankRc, $bodyW);
                    if ($laidFill === null) break;

                    $currentPlacedBands[] = new PlacedBandRecord(
                        $cb,
                        $margins->left,
                        $currentY,
                        $bodyW,
                        $laidFill->height,
                        $cb->fill,
                        $laidFill->elements
                    );
                    $currentBandHtml .= sprintf('<div style="position:absolute;left:%.2fmm;top:%.2fmm;width:%.2fmm;height:%.2fmm;">%s</div>',
                        $margins->left, $currentY, $bodyW, $laidFill->height, $laidFill->html);
                    $currentY += $laidFill->height;
                }
            }

            // DataFooter
            if ($dataBand->footer !== null) {
                $dfRc = new RenderCtx();
                $dfRc->data = $data;
                $dfRc->params = $parameters;
                $dfRc->pageNumber = $pageNum;
                $dfRc->totalPages = $totalPages;

                $laidDf = BandLayout::layout($dataBand->footer, $dfRc, $bodyW);
                if ($laidDf !== null) {
                    if ($currentY + $laidDf->height > $maxBodyY) {
                        $startNewPage();
                    }

                    $currentPlacedBands[] = new PlacedBandRecord(
                        $dataBand->footer,
                        $margins->left,
                        $currentY,
                        $bodyW,
                        $laidDf->height,
                        $dataBand->footer->fill,
                        $laidDf->elements
                    );
                    $currentBandHtml .= sprintf('<div style="position:absolute;left:%.2fmm;top:%.2fmm;width:%.2fmm;height:%.2fmm;">%s</div>',
                        $margins->left, $currentY, $bodyW, $laidDf->height, $laidDf->html);
                    $currentY += $laidDf->height;
                }
            }
        }

        // 4. ReportSummary
        if ($pageTpl->reportSummary !== null) {
            $sumRc = new RenderCtx();
            $sumRc->data = $data;
            $sumRc->params = $parameters;
            $sumRc->pageNumber = $pageNum;
            $sumRc->totalPages = $totalPages;

            $laidSum = BandLayout::layout($pageTpl->reportSummary, $sumRc, $bodyW);
            if ($laidSum !== null) {
                if ($currentY + $laidSum->height > $maxBodyY) {
                    $startNewPage();
                }

                $currentPlacedBands[] = new PlacedBandRecord(
                    $pageTpl->reportSummary,
                    $margins->left,
                    $currentY,
                    $bodyW,
                    $laidSum->height,
                    $pageTpl->reportSummary->fill,
                    $laidSum->elements
                );
                $currentBandHtml .= sprintf('<div style="position:absolute;left:%.2fmm;top:%.2fmm;width:%.2fmm;height:%.2fmm;">%s</div>',
                    $margins->left, $currentY, $bodyW, $laidSum->height, $laidSum->html);
                $currentY += $laidSum->height;
            }
        }

        // Flush final page
        $flushPage();

        return $pages;
    }

    private static function resolveRecords(DataBand $band, mixed $data): array
    {
        if ($data === null) return [];

        $path = $band->dataPath !== '' ? $band->dataPath : $band->dataset;
        $list = $path !== '' ? ExpressionEngine::getPath($data, $path) : $data;

        if (is_array($list)) {
            // Check if associative array
            if (array_keys($list) !== range(0, count($list) - 1) && !empty($list)) {
                return [$list];
            }
            return $list;
        }

        if (is_iterable($list)) {
            return iterator_to_array($list);
        }

        if ($list !== null) {
            return [$list];
        }

        return [];
    }
}
