<?php

namespace App\Support\Pdf;

interface PdfRenderer
{
    /** Renders a complete, self-contained HTML document (styles and fonts inlined) to PDF bytes. */
    public function render(string $html): string;
}
