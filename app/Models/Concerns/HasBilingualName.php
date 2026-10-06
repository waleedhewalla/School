<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * For models with name_ar / name_en columns: exposes a "name" attribute
 * in the active locale, falling back to Arabic.
 */
trait HasBilingualName
{
    protected function name(): Attribute
    {
        return Attribute::get(function () {
            $localized = $this->getAttribute('name_'.app()->getLocale());

            return filled($localized) ? $localized : $this->getAttribute('name_ar');
        });
    }
}
