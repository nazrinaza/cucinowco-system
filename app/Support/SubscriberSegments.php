<?php

namespace App\Support;

use App\Models\Subscriber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SubscriberSegments
{
    /**
     * @return array<int, string>
     */
    public function normalize(array|string|null $segments): array
    {
        if (is_string($segments)) {
            $segments = preg_split('/[|;,]+/', $segments) ?: [];
        }

        return collect($segments ?? [])
            ->filter(fn (mixed $segment): bool => is_string($segment))
            ->map(fn (string $segment): string => Str::limit(Str::squish(strip_tags($segment)), 50, ''))
            ->filter()
            ->unique(fn (string $segment): string => Str::lower($segment))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    public function all(): Collection
    {
        return Subscriber::query()
            ->whereNotNull('segments')
            ->get(['segments'])
            ->pluck('segments')
            ->flatten()
            ->pipe(fn (Collection $segments): Collection => collect($this->normalize($segments->all())));
    }

    /**
     * Match subscribers belonging to any selected segment.
     *
     * @param  array<int, string>  $segments
     */
    public function constrain(Builder $query, array $segments): Builder
    {
        $segments = $this->normalize($segments);

        if ($segments === []) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($segments): void {
            foreach ($segments as $segment) {
                $query->orWhereJsonContains('segments', $segment);
            }
        });
    }
}
