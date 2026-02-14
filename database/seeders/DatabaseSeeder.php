<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\CaptchaService;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create default admin user
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Admin user created: admin@example.com / password');

        // Create default CAPTCHA services
        CaptchaService::firstOrCreate(
            ['provider' => '2captcha'],
            [
                'name' => '2Captcha',
                'api_key' => '',
                'is_active' => false,
                'priority' => 1,
            ]
        );

        CaptchaService::firstOrCreate(
            ['provider' => 'capsolver'],
            [
                'name' => 'CapSolver',
                'api_key' => '',
                'is_active' => false,
                'priority' => 2,
            ]
        );

        $this->command->info('Default CAPTCHA services created');

        // Create default settings
        $defaultSettings = [
            ['key' => 'bot_concurrency', 'value' => '5', 'type' => 'integer', 'group' => 'bot'],
            ['key' => 'bot_timeout', 'value' => '30000', 'type' => 'integer', 'group' => 'bot'],
            ['key' => 'proxy_rotation_strategy', 'value' => 'round_robin', 'type' => 'string', 'group' => 'proxy'],
            ['key' => 'max_proxy_failures', 'value' => '3', 'type' => 'integer', 'group' => 'proxy'],
            ['key' => 'screenshot_on_error', 'value' => 'true', 'type' => 'boolean', 'group' => 'screenshot'],
            ['key' => 'screenshot_on_success', 'value' => 'false', 'type' => 'boolean', 'group' => 'screenshot'],
        ];

        foreach ($defaultSettings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }

        $this->command->info('Default settings created');

    
        // Run mock web target seeder
        $this->call([
            MockWebTargetSeeder::class,
            MockDataEntriesSeeder::class,
            MockBintaroWebTargetSeeder::class,
            MockSerpongWebTargetSeeder::class,
            MockButikDataEntriesSeeder::class,
        ]);
    }
}
