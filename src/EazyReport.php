<?php

namespace EazyReport;

class EazyReport
{
    public const VERSION = '1.0.0';

    /**
     * Load an EazyReport template from file path, JSON string, or stream.
     */
    public static function load(string $templateOrPath): ReportBuilder
    {
        return ReportBuilder::fromTemplate($templateOrPath);
    }

    /**
     * Load an EazyReport template from an array structure.
     */
    public static function fromArray(array $data): ReportBuilder
    {
        return ReportBuilder::fromArray($data);
    }

    /**
     * Quick helper to render a template directly to HTML.
     */
    public static function renderHtml(string $templateOrPath, mixed $data = null, array $parameters = [], array $options = []): string
    {
        return self::load($templateOrPath)
            ->data($data)
            ->params($parameters)
            ->toHtml($options);
    }

    /**
     * Quick helper to render a template directly to PDF bytes.
     */
    public static function renderPdf(string $templateOrPath, mixed $data = null, array $parameters = [], array $options = []): string
    {
        return self::load($templateOrPath)
            ->data($data)
            ->params($parameters)
            ->toPdf($options);
    }
}
