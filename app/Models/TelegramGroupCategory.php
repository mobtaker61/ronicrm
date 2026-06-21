<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;

class TelegramGroupCategory extends Model
{
    use BelongsToOrganization;

    protected $fillable = ['organization_id', 'name', 'sort_order'];

    public function groups()
    {
        return $this->hasMany(TelegramGroup::class, 'telegram_group_category_id');
    }
}
