<?php

namespace App\Support;

/**
 * تبدیل نام کامل ذخیره‌شده در CRM به ساختار نزدیک Gmail / Google Contacts.
 */
class FullNameParser
{
    /**
     * @return array{given: string, middle: ?string, family: ?string, display: string}
     */
    public static function parse(?string $fullName): array
    {
        $display = trim(preg_replace('/\s+/u', ' ', (string) $fullName));
        if ($display === '') {
            return [
                'given' => 'Unknown',
                'middle' => null,
                'family' => null,
                'display' => 'Unknown',
            ];
        }

        $parts = preg_split('/\s+/u', $display, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($parts);

        if ($n === 1) {
            return [
                'given' => $parts[0],
                'middle' => null,
                'family' => null,
                'display' => $display,
            ];
        }

        if ($n === 2) {
            return [
                'given' => $parts[0],
                'middle' => null,
                'family' => $parts[1],
                'display' => $display,
            ];
        }

        $given = $parts[0];
        $family = $parts[$n - 1];
        $middleParts = array_slice($parts, 1, $n - 2);
        $middle = $middleParts !== [] ? implode(' ', $middleParts) : null;

        return [
            'given' => $given,
            'middle' => $middle,
            'family' => $family,
            'display' => $display,
        ];
    }
}
