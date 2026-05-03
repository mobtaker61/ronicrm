<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class OrganizationNotificationService
{
    public const SETTINGS_KEY = 'org_notifications';

    public const EVENT_CUSTOMER_CREATED = 'customer_created';

    /**
     * متغیرهای مجاز برای قالب‌ها (کلید => توضیح)
     *
     * @return array<string, string>
     */
    public function allowedVariablesForEvent(string $event): array
    {
        // کلیدها باید دقیقا همان placeholder داخل متن باشند (مثلاً {name})
        $base = [
            '{name}' => 'نام مخاطب',
            '{company}' => 'نام شرکت مخاطب',
            '{email}' => 'ایمیل مخاطب',
            '{phone}' => 'شماره مخاطب',
            '{public_link}' => 'لینک پابلیک کارت مخاطب',
            '{org_name}' => 'نام سازمان',
        ];

        return match ($event) {
            self::EVENT_CUSTOMER_CREATED => $base,
            default => $base,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return [
            'version' => 1,
            'events' => [
                self::EVENT_CUSTOMER_CREATED => [
                    'enabled' => false,
                    'channels' => [
                        'email' => [
                            'enabled' => false,
                            'subject' => 'خوش آمدید {name}',
                            'body' => "سلام {name}\n\nبه {org_name} خوش آمدید.\nلینک کارت شما: {public_link}",
                        ],
                        'whatsapp' => [
                            'enabled' => false,
                            'body' => "سلام {name}\nبه {org_name} خوش آمدید.\n{public_link}",
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getSettingsForOrganization(?int $organizationId = null): array
    {
        $stored = Setting::getForOrganization(self::SETTINGS_KEY, null, $organizationId);
        $defaults = $this->defaults();
        if (! is_array($stored) || $stored === []) {
            return $defaults;
        }

        // merge minimal (preserve new defaults)
        return array_replace_recursive($defaults, $stored);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    public function saveSettingsForOrganization(array $settings, ?int $organizationId = null): void
    {
        Setting::setForOrganization(self::SETTINGS_KEY, $settings, $organizationId);
    }

    public function handleCustomerCreated(Customer $customer): void
    {
        $orgId = (int) ($customer->organization_id ?? 0);
        if ($orgId <= 0) {
            return;
        }

        $settings = $this->getSettingsForOrganization($orgId);
        $event = $settings['events'][self::EVENT_CUSTOMER_CREATED] ?? null;
        if (! is_array($event) || ! ($event['enabled'] ?? false)) {
            return;
        }

        $renderer = app(OrganizationTemplateRenderer::class);
        $allowedVars = $this->allowedVariablesForEvent(self::EVENT_CUSTOMER_CREATED);

        $channels = $event['channels'] ?? [];
        if (! is_array($channels)) {
            return;
        }

        // Email channel
        $emailCfg = $channels['email'] ?? null;
        if (is_array($emailCfg) && ($emailCfg['enabled'] ?? false)) {
            $toEmail = $this->resolveCustomerEmail($customer);
            if ($toEmail) {
                $subjectTpl = (string) ($emailCfg['subject'] ?? '');
                $bodyTpl = (string) ($emailCfg['body'] ?? '');
                $subject = $renderer->renderCustomerTemplate($subjectTpl, $customer);
                $body = $renderer->renderCustomerTemplate($bodyTpl, $customer);

                $unknown = array_merge(
                    $renderer->unknownVariables($subjectTpl, $allowedVars),
                    $renderer->unknownVariables($bodyTpl, $allowedVars),
                );
                if ($unknown !== []) {
                    Log::warning('Org notification template has unknown variables (email)', [
                        'org_id' => $orgId,
                        'event' => self::EVENT_CUSTOMER_CREATED,
                        'unknown' => $unknown,
                    ]);
                }

                try {
                    app(EmailService::class)->sendHtmlEmail($toEmail, $subject, $body);
                } catch (\Throwable $e) {
                    Log::warning('Org notification email send failed', [
                        'org_id' => $orgId,
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // WhatsApp channel
        $waCfg = $channels['whatsapp'] ?? null;
        if (is_array($waCfg) && ($waCfg['enabled'] ?? false)) {
            $toPhone = $this->resolveCustomerWhatsAppPhone($customer);
            if ($toPhone) {
                $bodyTpl = (string) ($waCfg['body'] ?? '');
                $message = $renderer->renderCustomerTemplate($bodyTpl, $customer);

                $unknown = $renderer->unknownVariables($bodyTpl, $allowedVars);
                if ($unknown !== []) {
                    Log::warning('Org notification template has unknown variables (whatsapp)', [
                        'org_id' => $orgId,
                        'event' => self::EVENT_CUSTOMER_CREATED,
                        'unknown' => $unknown,
                    ]);
                }

                try {
                    app(WhatsAppService::class)->sendMessage($toPhone, $message);
                } catch (\Throwable $e) {
                    Log::warning('Org notification whatsapp send failed', [
                        'org_id' => $orgId,
                        'customer_id' => $customer->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    protected function resolveCustomerEmail(Customer $customer): ?string
    {
        $email = trim((string) ($customer->email ?? ''));
        if ($email !== '') {
            return $email;
        }
        try {
            $c = $customer->contacts()->where('type', 'email')->orderByDesc('is_primary')->first();
            $v = $c?->value ? trim((string) $c->value) : '';
            return $v !== '' ? $v : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function resolveCustomerWhatsAppPhone(Customer $customer): ?string
    {
        $phone = trim((string) ($customer->phone ?? ''));
        if ($phone !== '') {
            return $phone;
        }
        try {
            $c = $customer->contacts()
                ->whereIn('type', ['whatsapp', 'phone'])
                ->orderByDesc('is_primary')
                ->first();
            $v = $c?->value ? trim((string) $c->value) : '';
            return $v !== '' ? $v : null;
        } catch (\Throwable) {
            return null;
        }
    }
}

