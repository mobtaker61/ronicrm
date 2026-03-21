<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    /** زبان‌های قابل انتخاب (نام نمایشی یکسان با فرم‌ها) */
    public const LANGUAGE_OPTIONS = [
        'Persian',
        'English',
        'Kurdish',
        'Turkish',
        'Arabic',
        'Hindi',
        'Urdu',
        'Other',
    ];

    protected $fillable = [
        'name',
        'type',
        'gender',
        'languages',
        'avatar',
        'company_name',
        'email',
        'phone',
        'address',
        'industry_id',
        'project_id',
        'status',
        'source',
        'contact_person',
        'notes',
        'share_key',
        'google_people_resource_name',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
            'source' => 'string',
            'gender' => 'string',
            'languages' => 'array',
        ];
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(CustomerContact::class);
    }

    public function socialMedia(): HasMany
    {
        return $this->hasMany(CustomerSocialMedia::class);
    }

    public function notes(): HasMany
    {
        return $this->hasMany(CustomerNote::class);
    }

    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(CampaignRecipient::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class);
    }

    public function telegramMessages(): HasMany
    {
        return $this->hasMany(TelegramMessage::class);
    }

    public function instagramMessages(): HasMany
    {
        return $this->hasMany(InstagramMessage::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($customer) {
            if (! $customer->share_key) {
                $customer->share_key = bin2hex(random_bytes(16));
            }
        });
    }
}
