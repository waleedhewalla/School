<?php

namespace App\Support\Pdf;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Gotenberg (https://gotenberg.dev): a small Docker service wrapping
 * headless Chromium, so Arabic shaping and RTL print exactly as in the
 * browser. Run it next to the app, e.g. `docker run -p 3000:3000 gotenberg/gotenberg:8`.
 */
class GotenbergPdfRenderer implements PdfRenderer
{
    public function __construct(private string $url) {}

    public function render(string $html): string
    {
        $response = Http::timeout(60)
            ->attach('files', $html, 'index.html')
            ->post(rtrim($this->url, '/').'/forms/chromium/convert/html', [
                'paperWidth' => '8.27',
                'paperHeight' => '11.7',
                'printBackground' => 'true',
                'preferCssPageSize' => 'true',
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gotenberg failed: HTTP '.$response->status());
        }

        return $response->body();
    }
}
