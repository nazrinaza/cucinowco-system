<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class AgentMediaFolder extends Model
{
    protected $fillable = ['name', 'parent_id', 'position'];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('name');
    }

    public function media(): HasMany
    {
        return $this->hasMany(AgentMedia::class, 'folder_id');
    }

    /** Root → this folder, for breadcrumbs. Guards against accidental cycles. */
    public function trail(): Collection
    {
        $trail = collect([$this]);
        $folder = $this;
        while ($folder->parent_id && $trail->count() < 10 && ($folder = $folder->parent)) {
            $trail->prepend($folder);
        }

        return $trail;
    }

    /** Folder plus every descendant id, used to block moving a folder inside itself. */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        $frontier = [$this->id];
        while ($frontier) {
            $frontier = self::whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    /** "Start here › Posters" style labels for admin dropdowns. */
    public static function options(): Collection
    {
        $all = self::orderBy('position')->orderBy('name')->get();
        $byParent = $all->groupBy(fn ($f) => $f->parent_id ?? 0);
        $out = collect();
        $walk = function ($parentId, $prefix) use (&$walk, $byParent, $out) {
            foreach ($byParent->get($parentId, []) as $folder) {
                $label = $prefix ? $prefix.' › '.$folder->name : $folder->name;
                $out->put($folder->id, $label);
                $walk($folder->id, $label);
            }
        };
        $walk(0, '');

        return $out;
    }
}
