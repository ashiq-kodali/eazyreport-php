<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default PDF Engine
    |--------------------------------------------------------------------------
    |
    | Supported: "auto", "native", "dompdf"
    |
    | - "auto": Uses Dompdf if installed, otherwise falls back to pure native vector PDF
    | - "native": Pure PHP zero-dependency vector PDF 1.4 generator
    | - "dompdf": Requires dompdf/dompdf
    |
    */
    'pdf_engine' => env('EAZYREPORT_PDF_ENGINE', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Templates Path
    |--------------------------------------------------------------------------
    |
    | Default directory where .rtpl report templates are stored.
    |
    */
    'templates_path' => resource_path('reports'),

    /*
    |--------------------------------------------------------------------------
    | Default Paper & Margins
    |--------------------------------------------------------------------------
    */
    'default_paper_size' => 'A4',
    'default_orientation' => 'portrait',
];
