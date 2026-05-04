<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LibraryTaxonomy extends Model
{
    protected $fillable = [
        'type',
        'name',
        'parent_id',
        'color',
        'description',
        'order'
    ];
}
