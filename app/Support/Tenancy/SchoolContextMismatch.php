<?php

namespace App\Support\Tenancy;

use LogicException;

class SchoolContextMismatch extends LogicException
{
    public static function forModel(string $model, ?int $expected, ?int $given): self
    {
        return new self(sprintf(
            'Refusing to save %s for school [%s] while the current school is [%s].',
            $model,
            $given ?? 'none',
            $expected ?? 'none',
        ));
    }
}
