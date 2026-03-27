<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    protected $fillable = ['code', 'name', 'sort_order', 'is_active', 'is_default', 'direction', 'font_family'];
}
