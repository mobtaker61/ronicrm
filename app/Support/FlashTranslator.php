<?php

namespace App\Support;

use App\Services\I18nService;

/**
 * Unified flash/backend messages: DB i18n first, then Laravel lang files.
 */
class FlashTranslator
{
  public static function get(string $key, array $replace = [], ?string $locale = null): string
  {
    $fullKey = str_starts_with($key, 'flash.') ? $key : 'flash.'.$key;
    $locale = $locale ?: app()->getLocale();

    $fromDb = app(I18nService::class)->translate($fullKey, '', $locale);
    if ($fromDb !== '' && $fromDb !== $fullKey) {
      return self::replacePlaceholders($fromDb, $replace);
    }

    $fromLang = __($fullKey, $replace, $locale);

    return $fromLang !== $fullKey ? $fromLang : $key;
  }

  /** @param array<string, string|int|float> $replace */
  protected static function replacePlaceholders(string $text, array $replace): string
  {
    foreach ($replace as $name => $value) {
      $text = str_replace(':'.$name, (string) $value, $text);
    }

    return $text;
  }
}
