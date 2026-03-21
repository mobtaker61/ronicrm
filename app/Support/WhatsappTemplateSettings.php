<?php

namespace App\Support;

/**
 * نرمال‌سازی JSON / آرایهٔ تنظیمات واتساپ برای تمپلیت و کمپین.
 */
class WhatsappTemplateSettings
{
    /**
     * @return array<string, mixed>|null
     */
    public static function normalizeFromRequest(?string $type, mixed $raw): ?array
    {
        if ($type !== 'whatsapp') {
            return null;
        }

        if ($raw === null || $raw === '') {
            return self::emptyStructure();
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($raw)) {
            return self::emptyStructure();
        }

        $gl = $raw['gender_labels'] ?? [];
        if (! is_array($gl)) {
            $gl = [];
        }

        $append = $raw['append_random_token'] ?? false;
        if (is_string($append)) {
            $append = filter_var($append, FILTER_VALIDATE_BOOLEAN);
        }

        return [
            'gender_labels' => [
                'male' => mb_substr(trim((string) ($gl['male'] ?? '')), 0, 120),
                'female' => mb_substr(trim((string) ($gl['female'] ?? '')), 0, 120),
                'other' => mb_substr(trim((string) ($gl['other'] ?? ($gl['others'] ?? ''))), 0, 120),
            ],
            'intro_phrases' => mb_substr(trim((string) ($raw['intro_phrases'] ?? '')), 0, 10000),
            'append_random_token' => (bool) $append,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function emptyStructure(): array
    {
        return [
            'gender_labels' => [
                'male' => '',
                'female' => '',
                'other' => '',
            ],
            'intro_phrases' => '',
            'append_random_token' => false,
        ];
    }
}
