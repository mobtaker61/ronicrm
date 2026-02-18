<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerContact;
use App\Models\CustomerSocialMedia;
use App\Models\SocialMediaType;

/**
 * Centralized customer lookup to prevent duplicates.
 * Checks: Telegram ID, normalized phone, Telegram username/handle.
 */
class CustomerMatchService
{
    /**
     * Normalize phone for comparison: digits only, optional + prefix.
     */
    public static function normalizePhone(?string $phone): string
    {
        if ($phone === null || trim($phone) === '') {
            return '';
        }
        return preg_replace('/\D/', '', trim($phone));
    }

    /**
     * Find existing customer by Telegram ID, phone, or handle.
     * Returns the first match found.
     */
    public static function findExistingByTelegram(?string $telegramId, ?string $username = null, ?string $phone = null): ?Customer
    {
        if ($telegramId !== null && $telegramId !== '') {
            $contact = CustomerContact::where('type', 'telegram')
                ->where('value', $telegramId)
                ->first();
            if ($contact?->customer) {
                return $contact->customer;
            }
        }

        if ($username !== null && trim($username) !== '') {
            $norm = ltrim(trim($username), '@');
            $contact = CustomerContact::where('type', 'telegram')
                ->where(function ($q) use ($norm) {
                    $q->where('value', $norm)
                        ->orWhere('value', '@' . $norm);
                })
                ->first();
            if ($contact?->customer) {
                return $contact->customer;
            }
            $tgTypeId = SocialMediaType::where('name', 'Telegram')->value('id');
            if ($tgTypeId) {
                $sm = CustomerSocialMedia::where('social_media_type_id', $tgTypeId)
                    ->where(function ($q) use ($norm) {
                        $q->where('handle', $norm)
                            ->orWhere('handle', '@' . $norm);
                    })
                    ->first();
                if ($sm?->customer) {
                    return $sm->customer;
                }
            }
        }

        $normPhone = self::normalizePhone($phone);
        if ($normPhone !== '' && strlen($normPhone) >= 10) {
            $matches = Customer::whereNotNull('phone')->where('phone', '!=', '')
                ->get()
                ->filter(fn ($c) => self::normalizePhone($c->phone) === $normPhone);
            if ($matches->isNotEmpty()) {
                return $matches->first();
            }
            $contact = CustomerContact::where('type', 'phone')->with('customer')->get()
                ->first(fn ($c) => self::normalizePhone($c->value) === $normPhone);
            if ($contact?->customer) {
                return $contact->customer;
            }
        }

        return null;
    }
}
