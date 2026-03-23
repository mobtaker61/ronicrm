<?php

namespace App\Models;

use App\Support\OrganizationContext;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->value : $default;
    }

    public static function set(string $key, $value): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => $value]
        );
    }

    public static function getForOrganization(string $key, $default = null, ?int $organizationId = null)
    {
        $orgId = $organizationId ?? OrganizationContext::getOrganizationId();
        if (! $orgId) {
            return $default;
        }

        $setting = OrganizationSetting::query()
            ->withoutGlobalScopes()
            ->where('organization_id', $orgId)
            ->where('key', $key)
            ->first();

        return $setting ? $setting->value : $default;
    }

    public static function setForOrganization(string $key, $value, ?int $organizationId = null): void
    {
        $orgId = $organizationId ?? OrganizationContext::getOrganizationId();
        if (! $orgId) {
            return;
        }

        OrganizationSetting::query()->withoutGlobalScopes()->updateOrCreate(
            ['organization_id' => $orgId, 'key' => $key],
            ['value' => $value]
        );
    }

    public static function getScoped(string $key, $default = null, ?int $organizationId = null)
    {
        $value = self::getForOrganization($key, null, $organizationId);
        if ($value !== null) {
            return $value;
        }

        return self::get($key, $default);
    }
}
