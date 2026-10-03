<?php

namespace EazyReport\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \EazyReport\ReportBuilder load(string $templateOrPath)
 * @method static \EazyReport\ReportBuilder fromArray(array $data)
 * @method static string renderHtml(string $templateOrPath, mixed $data = null, array $parameters = [], array $options = [])
 * @method static string renderPdf(string $templateOrPath, mixed $data = null, array $parameters = [], array $options = [])
 *
 * @see \EazyReport\EazyReport
 */
class EazyReport extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'eazyreport';
    }
}
