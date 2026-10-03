<?php

namespace EazyReport\Tests\Unit;

use EazyReport\EazyReport;
use EazyReport\Engine\BarcodeGenerator;
use EazyReport\Engine\ChartGenerator;
use EazyReport\Model\LayoutElement;
use EazyReport\ReportBuilder;
use PHPUnit\Framework\TestCase;

class BuilderTest extends TestCase
{
    private string $fixturePath;

    protected function setUp(): void
    {
        $this->fixturePath = __DIR__ . '/../Fixtures/test_invoice.rtpl';
    }

    public function testLoadTemplateFromFile(): void
    {
        $builder = EazyReport::load($this->fixturePath);
        $this->assertInstanceOf(ReportBuilder::class, $builder);

        $doc = $builder->getDocument();
        $this->assertEquals('Commercial Invoice', $doc->title);
        $this->assertNotEmpty($doc->pages);
    }

    public function testRenderHtmlAndPagination(): void
    {
        $data = [
            'invoice' => [
                'number' => 'INV-2026-999',
                'date' => '2026-10-03',
                'dueDate' => '2026-10-17',
                'notes' => 'Thank you for your business!',
            ],
            'customer' => [
                'name' => 'Acme Global Logistics',
                'address' => '456 Enterprise Blvd, Suite 800',
                'city' => 'Singapore',
                'country' => 'Singapore',
            ],
            'items' => [
                ['description' => 'Enterprise Cloud License', 'qty' => 10, 'rate' => 150.0, 'amount' => 1500.0],
                ['description' => 'Dedicated Migration Support', 'qty' => 20, 'rate' => 85.0, 'amount' => 1700.0],
                ['description' => 'SLA Platinum Maintenance', 'qty' => 1, 'rate' => 500.0, 'amount' => 500.0],
            ],
            'totals' => [
                'subtotal' => 3700.0,
                'tax' => 296.0,
                'total' => 3996.0,
            ]
        ];

        $builder = EazyReport::load($this->fixturePath)->data($data);
        $pages = $builder->getPages();

        $this->assertGreaterThanOrEqual(1, count($pages));
        $this->assertEquals(210.0, $pages[0]->width);
        $this->assertEquals(297.0, $pages[0]->height);

        $html = $builder->toHtml();
        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('INV-2026-999', $html);
        $this->assertStringContainsString('Acme Global Logistics', $html);
        $this->assertStringContainsString('Enterprise Cloud License', $html);
    }

    public function testRenderNativePdf(): void
    {
        $data = [
            'invoice' => ['number' => 'INV-8888'],
            'customer' => ['name' => 'Tech Corp'],
            'items' => [
                ['description' => 'Consulting Services', 'qty' => 5, 'rate' => 100.0, 'amount' => 500.0]
            ],
            'totals' => ['total' => 500.0]
        ];

        $builder = EazyReport::load($this->fixturePath)->data($data);
        $pdfBytes = $builder->toPdf(['native' => true]);

        $this->assertNotEmpty($pdfBytes);
        $this->assertStringStartsWith('%PDF-1.4', $pdfBytes);
        $this->assertStringContainsString('%%EOF', $pdfBytes);
    }

    public function testBarcodeAndChartGenerators(): void
    {
        // 1D Barcode (Code128)
        $code128Svg = BarcodeGenerator::generateSvg('code128', 'INV-999888', '#000000', '#ffffff', true);
        $this->assertStringContainsString('<svg', $code128Svg);
        $this->assertStringContainsString('rect', $code128Svg);

        // 2D QR Code
        $qrSvg = BarcodeGenerator::generateSvg('qrcode', 'https://eazyreport.in', '#000000', '#ffffff', false);
        $this->assertStringContainsString('<svg', $qrSvg);
        $this->assertStringContainsString('rect', $qrSvg);

        // Chart SVG
        $chartEl = new LayoutElement();
        $chartEl->chartType = 'column';
        $chartEl->chartTitle = 'Quarterly Sales';
        $chartEl->w = 120.0;
        $chartEl->h = 60.0;

        $chartSvg = ChartGenerator::generateSvg($chartEl, ['Q1', 'Q2', 'Q3', 'Q4'], [100, 200, 150, 300]);
        $this->assertStringContainsString('<svg', $chartSvg);
        $this->assertStringContainsString('Quarterly Sales', $chartSvg);
    }
}
