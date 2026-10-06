<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['isbn', 'title', 'author', 'publisher', 'published_year', 'category', 'shelf', 'copies'])]
class LibraryBook extends Model
{
    use BelongsToSchool;

    /** @return HasMany<LibraryLoan, $this> */
    public function loans(): HasMany
    {
        return $this->hasMany(LibraryLoan::class);
    }

    /** @return HasMany<LibraryLoan, $this> */
    public function openLoans(): HasMany
    {
        return $this->hasMany(LibraryLoan::class)->whereNull('returned_on');
    }
}
