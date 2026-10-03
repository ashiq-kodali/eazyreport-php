<?php

namespace EazyReport\Model;

use InvalidArgumentException;

class TemplateLoader
{
    public static function fromJson(string $json): LayoutDocument
    {
        if (trim($json) === '') {
            throw new InvalidArgumentException('Template JSON cannot be empty');
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('Invalid JSON provided: ' . json_last_error_msg());
        }

        return LayoutDocument::fromArray($decoded);
    }

    public static function fromFile(string $filePath): LayoutDocument
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("Template file not found at: {$filePath}");
        }

        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new InvalidArgumentException("Unable to read template file at: {$filePath}");
        }

        return self::fromJson($content);
    }
}
