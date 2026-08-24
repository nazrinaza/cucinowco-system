<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use RuntimeException;

class ReferenceNumber
{
    /** @param class-string<Model> $model */
    public static function make(string $prefix, string $model, string $column): string
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $reference = sprintf('%s-%s-%s', $prefix, now()->format('Ym'), Str::upper(Str::random(5)));

            if (! $model::query()->where($column, $reference)->exists()) {
                return $reference;
            }
        }

        throw new RuntimeException('Unable to generate a unique reference number.');
    }
}
