<?php

namespace Database\Seeders;

use App\Models\SocialMediaType;
use Illuminate\Database\Seeder;

class SocialMediaTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'Website',
                'icon' => 'fas fa-globe',
                'base_url' => null,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'Instagram',
                'icon' => 'fab fa-instagram',
                'base_url' => 'https://instagram.com/',
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Facebook',
                'icon' => 'fab fa-facebook',
                'base_url' => 'https://facebook.com/',
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'LinkedIn',
                'icon' => 'fab fa-linkedin',
                'base_url' => 'https://linkedin.com/in/',
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Twitter',
                'icon' => 'fab fa-twitter',
                'base_url' => 'https://twitter.com/',
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'YouTube',
                'icon' => 'fab fa-youtube',
                'base_url' => 'https://youtube.com/@',
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'TikTok',
                'icon' => 'fab fa-tiktok',
                'base_url' => 'https://tiktok.com/@',
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'WhatsApp',
                'icon' => 'fab fa-whatsapp',
                'base_url' => 'https://wa.me/',
                'is_active' => true,
                'sort_order' => 8,
            ],
            [
                'name' => 'Telegram',
                'icon' => 'fab fa-telegram',
                'base_url' => 'https://t.me/',
                'is_active' => true,
                'sort_order' => 9,
            ],
        ];

        foreach ($types as $type) {
            SocialMediaType::create($type);
        }
    }
}
