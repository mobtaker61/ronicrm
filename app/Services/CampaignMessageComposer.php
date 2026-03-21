<?php

namespace App\Services;

use App\Models\Customer;

/**
 * جایگزینی متغیرهای پیام کمپین و گزینه‌های ضد-تشابه برای واتساپ.
 */
class CampaignMessageComposer
{
    /**
     * @param  array<string, mixed>|null  $whatsappSettings  از campaign_templates یا campaigns
     * @param  bool  $applyWhatsappExtras  intro تصادفی و کد انتهایی فقط برای نوع whatsapp
     */
    public function render(
        string $content,
        Customer $customer,
        ?array $whatsappSettings,
        bool $applyWhatsappExtras = false
    ): string {
        $settings = is_array($whatsappSettings) ? $whatsappSettings : [];
        $genderLabels = $applyWhatsappExtras
            ? $this->normalizeGenderLabels($settings['gender_labels'] ?? [])
            : [];

        $genderOut = $this->resolveGenderDisplay($customer, $genderLabels, $applyWhatsappExtras);

        $replacements = [
            '{name}' => (string) $customer->name,
            '{company}' => (string) ($customer->company_name ?? ''),
            '{email}' => (string) ($customer->email ?? ''),
            '{phone}' => (string) ($customer->phone ?? ''),
            '{gender}' => $genderOut,
        ];

        $message = str_replace(array_keys($replacements), array_values($replacements), $content);

        if ($applyWhatsappExtras) {
            $intro = $this->pickRandomIntro($settings['intro_phrases'] ?? '');
            $message = str_replace('{intro}', $intro, $message);

            if (! empty($settings['append_random_token'])) {
                $message = rtrim($message).' '.$this->randomEightDigitToken();
            }
        } else {
            $message = str_replace('{intro}', '', $message);
        }

        return $message;
    }

    /**
     * @param  array<string, mixed>  $labels
     * @return array<string, string>
     */
    protected function normalizeGenderLabels(array $labels): array
    {
        $out = [
            'male' => trim((string) ($labels['male'] ?? '')),
            'female' => trim((string) ($labels['female'] ?? '')),
            'other' => trim((string) ($labels['other'] ?? '')),
        ];
        if ($out['other'] === '' && isset($labels['others'])) {
            $out['other'] = trim((string) $labels['others']);
        }

        return $out;
    }

    protected function resolveGenderDisplay(Customer $customer, array $genderLabels, bool $useCustomLabels): string
    {
        if ($customer->type !== 'person' || empty($customer->gender)) {
            return '';
        }

        $g = strtolower((string) $customer->gender);

        if ($useCustomLabels && $genderLabels !== []) {
            $mapped = $genderLabels[$g] ?? '';

            return $mapped !== '' ? $mapped : $g;
        }

        return $g;
    }

    protected function pickRandomIntro(string|array $raw): string
    {
        if (is_array($raw)) {
            $parts = array_values(array_filter(array_map('trim', $raw), fn ($p) => $p !== ''));
        } else {
            $raw = trim($raw);
            if ($raw === '') {
                return '';
            }
            $split = preg_split('/[،,;|]+/u', $raw, -1, PREG_SPLIT_NO_EMPTY);
            $parts = $split ? array_values(array_filter(array_map('trim', $split), fn ($p) => $p !== '')) : [];
        }

        if ($parts === []) {
            return '';
        }

        return $parts[array_rand($parts)];
    }

    protected function randomEightDigitToken(): string
    {
        return sprintf('%08d', random_int(0, 99_999_999));
    }
}
