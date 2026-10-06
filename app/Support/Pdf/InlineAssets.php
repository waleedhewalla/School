<?php

namespace App\Support\Pdf;

use Illuminate\Support\Facades\File;

/**
 * The built print stylesheet with its fonts embedded as data URIs, so a
 * PDF service that can't reach this app still renders the Arabic font.
 */
class InlineAssets
{
    public static function css(string $entry = 'resources/css/print.css'): string
    {
        $manifestPath = public_path('build/manifest.json');
        if (! File::exists($manifestPath)) {
            return '';
        }

        $file = json_decode(File::get($manifestPath), true)[$entry]['file'] ?? null;
        if ($file === null) {
            return '';
        }

        $css = File::get(public_path('build/'.$file));

        return preg_replace_callback('#url\((["\']?)/build/(assets/[^)"\']+\.(woff2|woff))\1\)#', function (array $m) {
            $path = public_path('build/'.$m[2]);
            if ($m[3] !== 'woff2' || ! File::exists($path)) {
                return 'url("")'; // woff2 is enough for Chromium; skip the fallbacks.
            }

            return 'url("data:font/woff2;base64,'.base64_encode(File::get($path)).'")';
        }, $css);
    }
}
