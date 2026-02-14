<?php

namespace Database\Seeders;

use App\Models\Website;
use App\Models\FormStep;
use App\Models\FormField;
use Illuminate\Database\Seeder;

class MockBintaroWebTargetSeeder extends Seeder
{
    /**
     * Seed mock web target for Butik Emas Bintaro configuration for testing.
     * Based on bot-old selectors: #name, #ktp, #phone_number, #check, #check_2, #captcha-box, #captcha_input
     */
    public function run(): void
    {
        // Create the mock website for Bintaro
        $website = Website::updateOrCreate(
            ['slug' => 'mock-antam-bintaro'],
            [
                'name' => 'Mock Antam Butik Bintaro (Testing)',
                'base_url' => 'http://localhost:8082',
                'description' => 'Mock website untuk testing bot engine. Meniru halaman pendaftaran antrian Butik Emas Bintaro berdasarkan struktur antributikbintaro.com',
                'is_active' => true,
                'headers' => [
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'id-ID,id;q=0.9,en-US;q=0.8,en;q=0.7',
                ],
                'timeout' => 30000,
                'retry_attempts' => 8,
                'retry_delay' => 300,
                'concurrency_limit' => 5,
                'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'use_stealth' => true,
                'use_proxy' => false,
            ]
        );

        $this->command->info("Website created: {$website->name}");

        // ========================================
        // STEP 1: Register/Queue Form Page
        // ========================================
        $registerStep = FormStep::updateOrCreate(
            ['website_id' => $website->id, 'name' => 'Queue Registration Form'],
            [
                'order' => 1,
                'url_pattern' => '/register',
                'wait_for_selector' => '#name',
                'wait_timeout' => 20000,
                'action_type' => 'fill_form',
                'success_indicator' => '#ticket-number-display',
                'failure_indicator' => '#error-message',
                'success_message_selector' => '#ticket-number-display',
                'failure_message_selector' => '#error-message',
                'is_final_step' => true,

            ]
        );

        // Queue form fields - matching bot-old selectors
        $registerFields = [
            [
                'name' => 'name',
                'label' => 'Nama KTP',
                'selector' => '#name',
                'type' => 'text',
                'data_source_field' => 'nama',
                'is_required' => true,
                'order' => 1,
                'delay_before' => 100,
                'delay_after' => 300,
                'clear_before_fill' => true,
            ],
            [
                'name' => 'ktp',
                'label' => 'Nomor KTP (16 digit)',
                'selector' => '#ktp',
                'type' => 'text',
                'data_source_field' => 'nik',
                'is_required' => true,
                'order' => 2,
                'validation_regex' => '/^[0-9]{16}$/',
                'delay_before' => 100,
                'delay_after' => 300,
                'clear_before_fill' => true,
            ],
            [
                'name' => 'phone_number',
                'label' => 'Nomor HP',
                'selector' => '#phone_number',
                'type' => 'text',
                'data_source_field' => 'telepon',
                'is_required' => true,
                'order' => 3,
                'validation_regex' => '/^08[0-9]{8,11}$/',
                'delay_before' => 100,
                'delay_after' => 300,
                'clear_before_fill' => true,
            ],
            [
                'name' => 'check',
                'label' => 'Persetujuan KTP Asli',
                'selector' => '#check',
                'type' => 'checkbox',
                'default_value' => '1',
                'is_required' => true,
                'order' => 4,
                'delay_before' => 300,
                'delay_after' => 500,
            ],
            [
                'name' => 'check_2',
                'label' => 'Persetujuan Transaksi',
                'selector' => '#check_2',
                'type' => 'checkbox',
                'default_value' => '1',
                'is_required' => true,
                'order' => 5,
                'delay_before' => 300,
                'delay_after' => 500,
            ],
            [
                'name' => 'captcha_input',
                'label' => 'Captcha Text',
                'selector' => '#captcha_input',
                'type' => 'text',
                'is_required' => true,
                'order' => 6,
                'captcha_label_selector' => '#captcha-box',
                'captcha_config' => [
                    'type' => 'text_copy',
                    'source_selector' => '#captcha-box',
                    'extract_method' => 'textContent',
                    'trim' => true,
                ],
                'delay_before' => 500,
                'delay_after' => 1000,
                'clear_before_fill' => true,
            ],
            [
                'name' => 'submit_button',
                'label' => 'Tombol Daftar',
                'selector' => 'button[type="submit"]',
                'type' => 'click_button',
                'is_required' => true,
                'order' => 7,
                'delay_before' => 1000,
                'delay_after' => 2000,
            ],
        ];

        foreach ($registerFields as $fieldData) {
            FormField::updateOrCreate(
                ['form_step_id' => $registerStep->id, 'name' => $fieldData['name']],
                $fieldData
            );
        }

        $this->command->info("  - Form step created: {$registerStep->name} with " . count($registerFields) . " fields");

        // ========================================
        // STEP 2: Extract Result
        // ========================================
        $resultStep = FormStep::updateOrCreate(
            ['website_id' => $website->id, 'name' => 'Extract Ticket Result'],
            [
                'order' => 2,
                'url_pattern' => null, // STAY on current page after Step 1 submit
                'wait_for_selector' => '.card', // Wait for card wrapper (always present)
                'wait_timeout' => 15000,
                'action_type' => 'extract_data',
                'success_indicator' => '#ticket-number-display',
                'failure_indicator' => '#error-message',
                'success_message_selector' => '#ticket-number-display',
                'failure_message_selector' => '#error-message',
                'is_final_step' => true,

            ]
        );

        // Result extraction fields
        $resultFields = [
            [
                'name' => 'ticket_number',
                'label' => 'Nomor Tiket',
                'selector' => '#ticket-number-display',
                'type' => 'text',
                'is_required' => false,
                'order' => 1,
            ],
            [
                'name' => 'error_message',
                'label' => 'Pesan Error',
                'selector' => '#error-message',
                'type' => 'text',
                'is_required' => false,
                'order' => 2,
            ],
        ];

        foreach ($resultFields as $fieldData) {
            FormField::updateOrCreate(
                ['form_step_id' => $resultStep->id, 'name' => $fieldData['name']],
                $fieldData
            );
        }

        $this->command->info("  - Form step created: {$resultStep->name} with " . count($resultFields) . " fields");

        // Clean up old steps that should not exist (e.g., stale 'Extract Ticket Result')
        $staleSteps = FormStep::where('website_id', $website->id)
            ->where('name', '!=', 'Queue Registration Form')
            ->get();
        foreach ($staleSteps as $staleStep) {
            FormField::where('form_step_id', $staleStep->id)->delete();
            $staleStep->delete();
            $this->command->warn("Deleted stale step: {$staleStep->name}");
        }

        $this->command->info(" Mock Bintaro Web Target seeder completed!");
        $this->command->newLine();
        $this->command->info(" CSS Selectors Reference:");
        $this->command->info("   Form Fields:");
        $this->command->info("   - Name:        #name");
        $this->command->info("   - NIK/KTP:     #ktp");
        $this->command->info("   - Phone:       #phone_number");
        $this->command->info("   - Checkbox 1:  #check");
        $this->command->info("   - Checkbox 2:  #check_2");
        $this->command->info("   - Captcha Box: #captcha-box");
        $this->command->info("   - Captcha In:  #captcha_input");
        $this->command->info("   - Submit:      button[type=\"submit\"]");
        $this->command->newLine();
        $this->command->info("   Result Detection:");
        $this->command->info("   - Success:     #ticket-number-display");
        $this->command->info("   - Error:       #error-message");
        $this->command->info("   - Stock Error: body contains 'STOK TIDAK TERSEDIA'");
        $this->command->info("   - NIK Error:   body contains 'NIK sudah terdaftar'");
        $this->command->newLine();
        $this->command->warn("  Pastikan mock server berjalan di http://localhost:8082");
    }
}
