<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramGroupCategory extends Model
{
    protected $fillable = ['name', 'sort_order'];

    public function groups()
    {
        return $this->hasMany(TelegramGroup::class, 'telegram_group_category_id');
    }
}
