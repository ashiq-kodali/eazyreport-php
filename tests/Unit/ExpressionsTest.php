<?php

namespace EazyReport\Tests\Unit;

use EazyReport\Expressions\ExpressionEngine;
use EazyReport\Expressions\Formatters;
use EazyReport\Expressions\NumberToWords;
use PHPUnit\Framework\TestCase;

class ExpressionsTest extends TestCase
{
    public function testNumberToWords(): void
    {
        $this->assertEquals('Twelve Thousand Three Hundred Forty-Five and 67/100', NumberToWords::convert(12345.67));
        $this->assertEquals('Zero and 00/100', NumberToWords::convert(0));
        $this->assertEquals('One Million and 00/100', NumberToWords::convert(1000000));
    }

    public function testFormatCurrency(): void
    {
        $this->assertEquals('$1,234.50', Formatters::formatCurrency(1234.5, '$', 2));
        $this->assertEquals('€500.00', Formatters::formatCurrency(500, '€', 2));
    }

    public function testFormatNumber(): void
    {
        $this->assertEquals('1,234.56', Formatters::formatNumber(1234.56, 2, true));
        $this->assertEquals('1234.56', Formatters::formatNumber(1234.56, 2, false));
    }

    public function testHandlebarsExpressions(): void
    {
        $data = [
            'customer' => ['name' => 'John Doe'],
            'total' => 2500.75,
            'items' => [
                ['desc' => 'Item 1', 'price' => 10],
                ['desc' => 'Item 2', 'price' => 20],
            ],
            'isActive' => true,
        ];

        // Path resolution
        $tpl = "Hello {{customer.name}}";
        $this->assertEquals("Hello John Doe", ExpressionEngine::evaluateTemplate($tpl, $data));

        // Format helpers
        $tpl2 = "Total: {{currency total}}";
        $this->assertEquals("Total: $2,500.75", ExpressionEngine::evaluateTemplate($tpl2, $data));

        $tpl3 = "Amount: {{words 1234}}";
        $this->assertStringContainsString("One Thousand Two Hundred Thirty-Four", ExpressionEngine::evaluateTemplate($tpl3, $data));

        // Upper helper
        $tpl4 = "{{upper customer.name}}";
        $this->assertEquals("JOHN DOE", ExpressionEngine::evaluateTemplate($tpl4, $data));

        // If block
        $tpl5 = "{{#if isActive}}Active!{{/if}}";
        $this->assertEquals("Active!", ExpressionEngine::evaluateTemplate($tpl5, $data));

        // Each block
        $tpl6 = "{{#each items}}{{this.desc}}:{{this.price}} {{/each}}";
        $this->assertEquals("Item 1:10 Item 2:20 ", ExpressionEngine::evaluateTemplate($tpl6, $data));
    }

    public function testArithmeticEvaluation(): void
    {
        $data = ['price' => 100, 'qty' => 5, 'discount' => 20];
        $res = ExpressionEngine::evaluateExpression('(price * qty) - discount', $data);
        $this->assertEquals(480, $res);
    }
}
