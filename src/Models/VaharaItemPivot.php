<?php

namespace Vaharadev\LaravelClient\Models;

use Illuminate\Database\Eloquent\Model;

class VaharaItemPivot extends Model
{
    protected $table = 'vahara_item_pivot';
    public $timestamps = true;
    protected $guarded = [];
}

