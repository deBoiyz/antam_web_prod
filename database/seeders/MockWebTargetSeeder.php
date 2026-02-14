<?php

namespace Database\Seeders;

use App\Models\Website;
use App\Models\FormStep;
use App\Models\FormField;
use Illuminate\Database\Seeder;

class MockWebTargetSeeder extends Seeder
{
    /**
     * Seed mock web target website configuration for testing.
     */
    public function run(): void
    {
        // Create the mock website
        $website = Website::updateOrCreate(
            ['slug' => 'mock-antam-belm'],
            [
                'name' => 'Mock Antam BELM (Testing)',
                'base_url' => 'http://localhost:8080',
                'description' => 'Mock website untuk testing bot engine. Meniru halaman Antrean Butik Emas Logam Mulia (BELM) Antam.',
                'is_active' => true,
                'headers' => json_encode([
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                ]),
                'timeout' => 30000,
                'retry_attempts' => 3,
                'retry_delay' => 2000,
                'concurrency_limit' => 5,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'use_stealth' => true,
                'use_proxy' => false,
            ]
        );

        $this->command->info("Website created: {$website->name}");

        // ========================================
        // STEP 1: Register Page
        // ========================================
        $registerStep = FormStep::updateOrCreate(
            ['website_id' => $website->id, 'name' => 'Register Form'],
            [
                'order' => 1,
                'url_pattern' => '/register',
                'wait_for_selector' => 'form',
                'wait_timeout' => 10000,
                'action_type' => 'fill_form',
                'success_indicator' => '.alert-success',
                'failure_indicator' => '.alert-error',
                'success_message_selector' => '.alert-success',
                'failure_message_selector' => '.alert-error',
                'is_final_step' => false,
            ]
        );

        // Register form fields
        $registerFields = [
            [
                'name' => 'nik',
                'label' => 'NIK',
                'selector' => 'input#nik',
                'type' => 'text',
                'data_source_field' => 'nik',
                'is_required' => true,
                'order' => 1,
                'validation_regex' => '/^[0-9]{16}$/',
                'delay_before' => 100,
                'delay_after' => 200,
                'clear_before_fill' => true,
            ],
            [
                'name' => 'nama',
                'label' => 'Nama Lengkap Sesuai KTP',
                'selector' => 'input#nama',
                'type' => 'text',
                'data_source_field' => 'nama',
                'is_required' => true,
                'order' => 2,
                'delay_before' => 100,
                'delay_after' => 200,
                'clear_before_fill' => true,
            ],
            [
                'name' => 'whatsapp',
                'label' => 'WhatsApp',
                'selector' => 'input#whatsapp',
                'type' => 'text',
                'data_source_field' => 'whatsapp',
                'is_required' => true,
                'order' => 3,
                'validation_regex' => '/^[0-9]{10,15}$/',
                'delay_before' => 100,
                'delay_after' => 200,
                'clear_before_fill' => true,
            ],
            [
                'name' => 'email',
                'label' => 'E-mail',
                'selector' => 'input#email',
                'type' => 'email',
                'data_source_field' => 'email',
                'is_required' => true,
                'order' => 4,
                'delay_before' => 100,
                'delay_after' => 200,
                'clear_before_fill' => true,
            ],
            [
                'name' => 'password',
                'label' => 'Password',
                'selector' => 'input#password',
                'type' => 'password',
                'data_source_field' => 'password',
                'is_required' => true,
                'order' => 5,
                'delay_before' => 100,
                'delay_after' => 200,
                'clear_before_fill' => true,
            ],
            [
                'name' => 'captcha',
                'label' => 'Captcha Aritmatika',
                'selector' => 'input#captcha',
                'type' => 'captcha_arithmetic',
                'is_required' => true,
                'order' => 6,
                'captcha_label_selector' => 'label[for="captcha"]',
                'captcha_config' => [
                    'pattern' => '/Berapa hasil perhitungan dari (\d+) (ditambah|dikurangi|dikali) (\d+)/',
                    'operators' => [
                        'ditambah' => '+',
                        'dikurangi' => '-',
                        'dikali' => '*',
                    ],
                ],
                'delay_before' => 500,
                'delay_after' => 300,
                'clear_before_fill' => true,
            ],
            [
                'name' => 'terms',
                'label' => 'Syarat dan Ketentuan',
                'selector' => 'input#terms',
                'type' => 'checkbox',
                'is_required' => true,
                'order' => 7,
                'default_value' => 'on',
                'delay_before' => 200,
                'delay_after' => 300,
            ],
            [
                'name' => 'submit_register',
                'label' => 'Tombol Register',
                'selector' => 'button[type="submit"]',
                'type' => 'click_button',
                'is_required' => true,
                'order' => 8,
                'delay_before' => 500,
                'delay_after' => 1000,
            ],
        ];

        foreach ($registerFields as $field) {
            FormField::updateOrCreate(
                ['form_step_id' => $registerStep->id, 'name' => $field['name']],
                $field
            );
        }

        $this->command->info("  - Register step created with " . count($registerFields) . " fields");

        // ========================================
        // STEP 2: Login Page
        // ========================================
        $loginStep = FormStep::updateOrCreate(
            ['website_id' => $website->id, 'name' => 'Login Form'],
            [
                'order' => 2,
                'url_pattern' => '/login',
                'wait_for_selector' => 'form',
                'wait_timeout' => 10000,
                'action_type' => 'fill_form',
                'success_indicator' => null, // Will redirect to dashboard
                'failure_indicator' => '.alert-error',
                'success_message_selector' => null,
                'failure_message_selector' => '.alert-error',
                'is_final_step' => false,
            ]
        );

        // Login form fields
        $loginFields = [
            [
                'name' => 'username',
                'label' => 'Username',
                'selector' => 'input#username',
                'type' => 'text',
                'data_source_field' => 'email',
                'is_required' => true,
                'order' => 1,
                'delay_before' => 100,
                'delay_after' => 200,
                'clear_before_fill' => true,
            ],
            [
                'name' => 'password',
                'label' => 'Password',
                'selector' => 'input#password',
                'type' => 'password',
                'data_source_field' => 'password',
                'is_required' => true,
                'order' => 2,
                'delay_before' => 100,
                'delay_after' => 200,
                'clear_before_fill' => true,
            ],
            [
                'name' => 'captcha',
                'label' => 'Captcha Aritmatika',
                'selector' => 'input#captcha',
                'type' => 'captcha_arithmetic',
                'is_required' => true,
                'order' => 3,
                'captcha_label_selector' => 'label[for="captcha"]',
                'captcha_config' => [
                    'pattern' => '/Berapa hasil perhitungan dari (\d+) (ditambah|dikurangi|dikali) (\d+)/',
                    'operators' => [
                        'ditambah' => '+',
                        'dikurangi' => '-',
                        'dikali' => '*',
                    ],
                ],
                'delay_before' => 500,
                'delay_after' => 300,
                'clear_before_fill' => true,
            ],
            [
                'name' => 'remember',
                'label' => 'Remember me',
                'selector' => 'input#remember',
                'type' => 'checkbox',
                'is_required' => false,
                'order' => 4,
                'default_value' => 'on',
                'delay_before' => 100,
                'delay_after' => 200,
            ],
            [
                'name' => 'submit_login',
                'label' => 'Tombol Login',
                'selector' => 'button[type="submit"]',
                'type' => 'click_button',
                'is_required' => true,
                'order' => 5,
                'delay_before' => 500,
                'delay_after' => 1000,
            ],
        ];

        foreach ($loginFields as $field) {
            FormField::updateOrCreate(
                ['form_step_id' => $loginStep->id, 'name' => $field['name']],
                $field
            );
        }

        $this->command->info("  - Login step created with " . count($loginFields) . " fields");

        // ========================================
        // STEP 3: Navigate to Antrean
        // ========================================
        $navigateAntreanStep = FormStep::updateOrCreate(
            ['website_id' => $website->id, 'name' => 'Navigate to Antrean'],
            [
                'order' => 3,
                'url_pattern' => '/dashboard',
                'wait_for_selector' => '.dashboard-menu',
                'wait_timeout' => 10000,
                'action_type' => 'click',
                'success_indicator' => null,
                'failure_indicator' => null,
                'is_final_step' => false,
            ]
        );

        $navigateFields = [
            [
                'name' => 'click_antrean',
                'label' => 'Klik Menu Antrean',
                'selector' => 'a.menu-card[href="/antrean"]',
                'type' => 'click_button',
                'is_required' => true,
                'order' => 1,
                'delay_before' => 500,
                'delay_after' => 1000,
            ],
        ];

        foreach ($navigateFields as $field) {
            FormField::updateOrCreate(
                ['form_step_id' => $navigateAntreanStep->id, 'name' => $field['name']],
                $field
            );
        }

        $this->command->info("  - Navigate to Antrean step created");

        // ========================================
        // STEP 4: Claim Ticket
        // ========================================
        $claimTicketStep = FormStep::updateOrCreate(
            ['website_id' => $website->id, 'name' => 'Claim Ticket'],
            [
                'order' => 4,
                'url_pattern' => '/antrean',
                'wait_for_selector' => '.ticket-status',
                'wait_timeout' => 10000,
                'action_type' => 'fill_form',
                'success_indicator' => '.ticket-result',
                'failure_indicator' => '.alert-error',
                'success_message_selector' => '.ticket-code',
                'failure_message_selector' => '.alert-error',
                'is_final_step' => true,
            ]
        );

        $claimFields = [
            [
                'name' => 'check_availability',
                'label' => 'Check Ticket Availability',
                'selector' => '.status-number',
                'type' => 'custom',
                'is_required' => false,
                'order' => 1,
                'custom_handler' => "
                    async (page, selector) => {
                        const availableText = await page.\$eval(selector, el => el.textContent);
                        const available = parseInt(availableText, 10);
                        return { available, canProceed: available > 0 };
                    }
                ",
                'delay_before' => 300,
                'delay_after' => 200,
            ],
            [
                'name' => 'submit_claim',
                'label' => 'Tombol Ambil Tiket',
                'selector' => 'form[action="/claim-ticket"] button[type="submit"]',
                'type' => 'click_button',
                'is_required' => true,
                'order' => 2,
                'delay_before' => 500,
                'delay_after' => 2000,
            ],
        ];

        foreach ($claimFields as $field) {
            FormField::updateOrCreate(
                ['form_step_id' => $claimTicketStep->id, 'name' => $field['name']],
                $field
            );
        }

        $this->command->info("  - Claim Ticket step created with " . count($claimFields) . " fields");

        // Clean up old Step 5 (Extract Result) if it exists - no longer needed
        // The success_message_selector on Step 4 now handles ticket extraction
        $oldStep5 = FormStep::where('website_id', $website->id)->where('name', 'Extract Result')->first();
        if ($oldStep5) {
            FormField::where('form_step_id', $oldStep5->id)->delete();
            $oldStep5->delete();
            $this->command->info("  - Removed old Step 5 (Extract Result) - no longer needed");
        }

        $this->command->newLine();
        $this->command->info("  Mock Web Target seeder completed!");
        $this->command->info("  Website: {$website->name}");
        $this->command->info("  Base URL: {$website->base_url}");
        $this->command->info("  Total Steps: 4");
        $this->command->newLine();
        $this->command->warn("  Pastikan mock server berjalan di http://localhost:8080");
    }
}
