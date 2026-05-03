<?php

namespace App\Services;

use App\Models\Customer;

class OrganizationTemplateRenderer
{
    /**
     * @param  array<string, string>  $extra
     */
    public function renderCustomerTemplate(string $content, Customer $customer, array $extra = []): string
    {
        $orgName = '';
        try {
            $orgName = (string) ($customer->organization?->name ?? '');
        } catch (\Throwable) {
            $orgName = '';
        }

        $publicLink = '';
        try {
            if (! empty($customer->share_key)) {
                $publicLink = (string) route('public.customer.card', $customer->share_key);
            }
        } catch (\Throwable) {
            $publicLink = '';
        }

        $replacements = array_merge([
            '{name}' => (string) ($customer->name ?? ''),
            '{company}' => (string) ($customer->company_name ?? ''),
            '{email}' => (string) ($customer->email ?? ''),
            '{phone}' => (string) ($customer->phone ?? ''),
            '{public_link}' => $publicLink,
            '{org_name}' => $orgName,
        ], $extra);

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * @param  array<string, string>  $allowedVarsMap
     * @return array<int, string> list of unknown variables like "{foo}"
     */
    public function unknownVariables(string $content, array $allowedVarsMap): array
    {
        if (! preg_match_all('/\{[a-z_]+\}/i', $content, $m)) {
            return [];
        }
        $vars = array_values(array_unique($m[0] ?? []));
        $unknown = [];
        foreach ($vars as $v) {
            if (! array_key_exists($v, $allowedVarsMap)) {
                $unknown[] = $v;
            }
        }

        return $unknown;
    }
}

