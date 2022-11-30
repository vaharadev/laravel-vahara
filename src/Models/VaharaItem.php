<?php

namespace Vaharadev\LaravelClient\Models;

use Illuminate\Database\Eloquent\Model;

class VaharaItem extends Model
{
    protected $table = 'vahara_items';
    public $timestamps = true;
    protected $guarded = [];
    protected $fillable = ["type", "project_key", "data"];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function parents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this
            ->belongsToMany(VaharaItem::class, 'vahara_item_pivot', 'child_id', 'parent_id')
            ->withPivot('relationship', 'sort');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function children(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this
            ->belongsToMany(VaharaItem::class, 'vahara_item_pivot', 'parent_id', 'child_id')
            ->withPivot('id', 'relationship', 'sort')
            ->orderBy('sort');
    }

    public function getDataAttribute($value)
    {
        return json_decode($value);
    }
}

