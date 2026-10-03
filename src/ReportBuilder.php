<?php

namespace EazyReport;

use EazyReport\Engine\PaginationEngine;
use EazyReport\Engine\RenderedPage;
use EazyReport\Model\LayoutDocument;
use EazyReport\Model\TemplateLoader;
use EazyReport\Renderers\HtmlRenderer;
use EazyReport\Renderers\PdfRenderer;

class ReportBuilder
{
    private LayoutDocument $doc;
    private mixed $data = null;
    private array $parameters = [];
    private array $options = [];
    /** @var RenderedPage[]|null */
    private ?array $renderedPages = null;

    public function __construct(LayoutDocument $doc)
    {
        $this->doc = $doc;
    }

    /**
     * Create a builder from a JSON string, file path, or stream.
     */
    public static function fromTemplate(string $templateOrPath): self
    {
        $doc = TemplateLoader::load($templateOrPath);
        return new self($doc);
    }

    /**
     * Create a builder from an associative array structure.
     */
    public static function fromArray(array $data): self
    {
        $doc = TemplateLoader::fromArray($data);
        return new self($doc);
    }

    /**
     * Set the main data payload (array, object, collection).
     */
    public function data(mixed $data): self
    {
        $this->data = $data;
        $this->renderedPages = null;
        return $this;
    }

    /**
     * Set multiple report parameters.
     */
    public function params(array $parameters): self
    {
        $this->parameters = array_merge($this->parameters, $parameters);
        $this->renderedPages = null;
        return $this;
    }

    /**
     * Set a single parameter.
     */
    public function param(string $key, mixed $value): self
    {
        $this->parameters[$key] = $value;
        $this->renderedPages = null;
        return $this;
    }

    /**
     * Set pagination or renderer options.
     */
    public function options(array $options): self
    {
        $this->options = array_merge($this->options, $options);
        $this->renderedPages = null;
        return $this;
    }

    /**
     * Build and paginate the report pages.
     *
     * @return RenderedPage[]
     */
    public function getPages(): array
    {
        if ($this->renderedPages === null) {
            $this->renderedPages = PaginationEngine::paginate(
                $this->doc,
                $this->data,
                $this->parameters,
                $this->options
            );
        }
        return $this->renderedPages;
    }

    /**
     * Get the total page count.
     */
    public function getPageCount(): int
    {
        return count($this->getPages());
    }

    /**
     * Render the report into an HTML string.
     */
    public function toHtml(array $options = []): string
    {
        $pages = $this->getPages();
        return HtmlRenderer::render($pages, array_merge($this->options, $options));
    }

    /**
     * Render the report into binary PDF string.
     */
    public function toPdf(array $options = []): string
    {
        $pages = $this->getPages();
        return PdfRenderer::render($pages, array_merge($this->options, $options));
    }

    /**
     * Save HTML to the specified file path.
     */
    public function saveHtml(string $filePath, array $options = []): bool
    {
        $html = $this->toHtml($options);
        return file_put_contents($filePath, $html) !== false;
    }

    /**
     * Save PDF to the specified file path.
     */
    public function savePdf(string $filePath, array $options = []): bool
    {
        $pdf = $this->toPdf($options);
        return file_put_contents($filePath, $pdf) !== false;
    }

    /**
     * Stream or download the PDF in a web response.
     */
    public function downloadPdf(string $filename = 'report.pdf', array $options = []): void
    {
        $pdf = $this->toPdf($options);
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf));
        }
        echo $pdf;
        exit;
    }

    /**
     * View the PDF inline in the browser.
     */
    public function streamPdf(string $filename = 'report.pdf', array $options = []): void
    {
        $pdf = $this->toPdf($options);
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf));
        }
        echo $pdf;
        exit;
    }

    /**
     * Get the underlying LayoutDocument.
     */
    public function getDocument(): LayoutDocument
    {
        return $this->doc;
    }
}
