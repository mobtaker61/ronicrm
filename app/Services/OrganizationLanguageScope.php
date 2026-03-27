<?php

namespace App\Services;

use App\Models\Language;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class OrganizationLanguageScope
{
    public static function restrictQuery(Builder $query, ?int $organizationId): Builder
    {
        if ($organizationId === null) {
            return $query;
        }

        $count = DB::table('language_organization')
            ->where('organization_id', $organizationId)
            ->count();

        if ($count === 0) {
            return $query;
        }

        return $query->whereIn('languages.id', function ($sub) use ($organizationId) {
            $sub->select('language_id')
                ->from('language_organization')
                ->where('organization_id', $organizationId);
        });
    }

    public static function isCodeAllowedForOrganization(string $code, ?int $organizationId): bool
    {
        $q = Language::query()->where('code', $code)->where('is_active', true);
        self::restrictQuery($q, $organizationId);

        return $q->exists();
    }
}
