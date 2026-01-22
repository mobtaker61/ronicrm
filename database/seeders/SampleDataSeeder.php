<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Customer;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Database\Seeder;

class SampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::where('email', 'admin@ronicrm.com')->first();
        $user = User::where('email', 'user@ronicrm.com')->first();

        if (!$admin || !$user) {
            $this->command->error('Please run DatabaseSeeder first to create users!');
            return;
        }

        // Create Industries
        $industries = [
            ['name' => 'Technology', 'description' => 'Technology and Software Companies', 'color' => '#3B82F6'],
            ['name' => 'Healthcare', 'description' => 'Healthcare and Medical Services', 'color' => '#10B981'],
            ['name' => 'Finance', 'description' => 'Banking and Financial Services', 'color' => '#F59E0B'],
            ['name' => 'Retail', 'description' => 'Retail and E-commerce', 'color' => '#EF4444'],
            ['name' => 'Education', 'description' => 'Educational Institutions', 'color' => '#8B5CF6'],
            ['name' => 'Manufacturing', 'description' => 'Manufacturing and Production', 'color' => '#6366F1'],
            ['name' => 'Real Estate', 'description' => 'Real Estate and Property', 'color' => '#EC4899'],
            ['name' => 'Hospitality', 'description' => 'Hotels and Restaurants', 'color' => '#14B8A6'],
        ];

        $industryModels = [];
        foreach ($industries as $industry) {
            $industryModels[] = Industry::create($industry);
        }

        $this->command->info('Created ' . count($industryModels) . ' industries.');

        // Create Sample Customers (Dubai-based)
        $customers = [
            [
                'name' => 'Ahmed Al Maktoum',
                'company_name' => 'Dubai Tech Solutions',
                'email' => 'ahmed@dubaitech.ae',
                'phone' => '+971501234567',
                'address' => 'Business Bay, Dubai, UAE',
                'industry_id' => $industryModels[0]->id,
                'status' => 'customer',
                'source' => 'website',
                'contact_person' => 'Ahmed Al Maktoum',
                'notes' => 'Long-term customer, very satisfied with services',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Fatima Al Zahra',
                'company_name' => 'Dubai Health Clinic',
                'email' => 'fatima@dubaihealth.ae',
                'phone' => '+971501876543',
                'address' => 'Jumeirah, Dubai, UAE',
                'industry_id' => $industryModels[1]->id,
                'status' => 'prospect',
                'source' => 'referral',
                'contact_person' => 'Fatima Al Zahra',
                'notes' => 'Interested in marketing services',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Mohammed Al Rashid',
                'company_name' => 'Emirates Bank',
                'email' => 'mohammed@emiratesbank.ae',
                'phone' => '+971488776655',
                'address' => 'Sheikh Zayed Road, Dubai, UAE',
                'industry_id' => $industryModels[2]->id,
                'status' => 'lead',
                'source' => 'advertisement',
                'contact_person' => 'Mohammed Al Rashid',
                'notes' => 'Needs follow-up',
                'created_by' => $user->id,
            ],
            [
                'name' => 'Zahra Al Mansoori',
                'company_name' => 'Dubai Fashion Store',
                'email' => 'zahra@dubaifashion.ae',
                'phone' => '+971501212345',
                'address' => 'Dubai Mall, Dubai, UAE',
                'industry_id' => $industryModels[3]->id,
                'status' => 'customer',
                'source' => 'social_media',
                'contact_person' => 'Zahra Al Mansoori',
                'notes' => 'Active on social media platforms',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Ali Al Nuaimi',
                'company_name' => 'Dubai University',
                'email' => 'ali@dubaiuniversity.ae',
                'phone' => '+971466554433',
                'address' => 'Academic City, Dubai, UAE',
                'industry_id' => $industryModels[4]->id,
                'status' => 'prospect',
                'source' => 'website',
                'contact_person' => 'Ali Al Nuaimi',
                'notes' => 'Negotiating contract terms',
                'created_by' => $user->id,
            ],
            [
                'name' => 'Sara Al Suwaidi',
                'company_name' => 'Dubai Manufacturing Co.',
                'email' => 'sara@dubaimanufacturing.ae',
                'phone' => '+971313456789',
                'address' => 'Dubai Industrial City, UAE',
                'industry_id' => $industryModels[5]->id,
                'status' => 'lead',
                'source' => 'other',
                'contact_person' => 'Sara Al Suwaidi',
                'notes' => 'Met at trade exhibition',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Hussein Al Falasi',
                'company_name' => 'Dubai Properties',
                'email' => 'hussein@dubaiproperties.ae',
                'phone' => '+971501345678',
                'address' => 'Downtown Dubai, UAE',
                'industry_id' => $industryModels[6]->id,
                'status' => 'customer',
                'source' => 'website',
                'contact_person' => 'Hussein Al Falasi',
                'notes' => 'VIP customer',
                'created_by' => $admin->id,
            ],
            [
                'name' => 'Maryam Al Qasimi',
                'company_name' => 'Burj Al Arab Hotel',
                'email' => 'maryam@burjalarab.ae',
                'phone' => '+971422334455',
                'address' => 'Jumeirah Beach, Dubai, UAE',
                'industry_id' => $industryModels[7]->id,
                'status' => 'prospect',
                'source' => 'referral',
                'contact_person' => 'Maryam Al Qasimi',
                'notes' => 'Referred by another customer',
                'created_by' => $user->id,
            ],
            [
                'name' => 'Reza Al Mazrouei',
                'company_name' => 'Dubai Tech Startup',
                'email' => 'reza@dubaistartup.ae',
                'phone' => '+971501456789',
                'address' => 'Dubai Internet City, UAE',
                'industry_id' => $industryModels[0]->id,
                'status' => 'lead',
                'source' => 'social_media',
                'contact_person' => 'Reza Al Mazrouei',
                'notes' => 'New startup company',
                'created_by' => $user->id,
            ],
            [
                'name' => 'Narges Al Shamsi',
                'company_name' => 'Dubai Education Center',
                'email' => 'narges@dubaieducation.ae',
                'phone' => '+971433445566',
                'address' => 'Knowledge Park, Dubai, UAE',
                'industry_id' => $industryModels[4]->id,
                'status' => 'customer',
                'source' => 'website',
                'contact_person' => 'Narges Al Shamsi',
                'notes' => 'Satisfied customer',
                'created_by' => $admin->id,
            ],
        ];

        $customerModels = [];
        foreach ($customers as $customer) {
            $customerModels[] = Customer::create($customer);
        }

        $this->command->info('Created ' . count($customerModels) . ' customers.');

        // Create Sample Campaigns
        $campaigns = [
            [
                'name' => 'Monthly Newsletter - January',
                'description' => 'Monthly newsletter to customers',
                'type' => 'email',
                'status' => 'completed',
                'subject' => 'Monthly Newsletter - January 2024',
                'content' => 'Dear Valued Customer, We are pleased to share our latest updates and special offers with you...',
                'created_by' => $admin->id,
                'started_at' => now()->subDays(5),
                'completed_at' => now()->subDays(4),
            ],
            [
                'name' => 'WhatsApp Marketing Campaign',
                'description' => 'Promotional message via WhatsApp',
                'type' => 'whatsapp',
                'status' => 'scheduled',
                'subject' => null,
                'content' => 'Hello! Special offer for you! Get 20% discount on all products...',
                'created_by' => $admin->id,
                'scheduled_at' => now()->addDays(3),
            ],
            [
                'name' => 'New Year Greetings',
                'description' => 'New Year greeting messages',
                'type' => 'whatsapp',
                'status' => 'completed',
                'subject' => null,
                'content' => 'Happy New Year! Wishing you a year full of success and happiness...',
                'created_by' => $user->id,
                'started_at' => now()->subDays(10),
                'completed_at' => now()->subDays(9),
            ],
            [
                'name' => 'New Product Launch Campaign',
                'description' => 'Introducing new product to customers',
                'type' => 'email',
                'status' => 'draft',
                'subject' => 'Introducing Our New Product',
                'content' => 'We are excited to introduce our new product to you...',
                'created_by' => $admin->id,
            ],
        ];

        $campaignModels = [];
        foreach ($campaigns as $campaign) {
            $campaignModels[] = Campaign::create($campaign);
        }

        $this->command->info('Created ' . count($campaignModels) . ' campaigns.');

        // Create Campaign Recipients for completed campaigns
        $customerCollection = collect($customerModels);
        foreach ($campaignModels as $index => $campaign) {
            if ($campaign->status === 'completed') {
                // Add recipients to completed campaigns
                $count = min(5, $customerCollection->count());
                $selectedCustomers = $customerCollection->random($count);
                if (!is_iterable($selectedCustomers)) {
                    $selectedCustomers = [$selectedCustomers];
                }
                foreach ($selectedCustomers as $customer) {
                    CampaignRecipient::create([
                        'campaign_id' => $campaign->id,
                        'customer_id' => $customer->id,
                        'status' => 'sent',
                        'sent_at' => $campaign->completed_at,
                    ]);
                }
            } elseif ($campaign->status === 'scheduled') {
                // Add recipients to scheduled campaigns
                $count = min(3, $customerCollection->count());
                $selectedCustomers = $customerCollection->random($count);
                if (!is_iterable($selectedCustomers)) {
                    $selectedCustomers = [$selectedCustomers];
                }
                foreach ($selectedCustomers as $customer) {
                    CampaignRecipient::create([
                        'campaign_id' => $campaign->id,
                        'customer_id' => $customer->id,
                        'status' => 'pending',
                    ]);
                }
            }
        }

        $this->command->info('Created campaign recipients.');
        $this->command->info('Sample data seeded successfully!');
    }
}
