<?php

namespace Database\Seeders;

use App\Models\Website;
use App\Models\DataEntry;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class MockDataEntriesSeeder extends Seeder
{
    /**
     * Seed sample data entries for mock web target testing.
     */
    public function run(): void
    {
        $website = Website::where('slug', 'mock-antam-belm')->first();

        if (!$website) {
            $this->command->error('Website mock-antam-belm not found. Run MockWebTargetSeeder first.');
            return;
        }

        $faker = Faker::create('id_ID');

        // Sample data entries for testing
        $sampleData = [
            // Test user that already exists in mock server
            [
                'nik' => '1234567890123456',
                'nama' => 'Test User',
                'whatsapp' => '081234567890',
                'email' => 'test@example.com',
                'password' => 'password123',
            ],
            // New users to register
            [
                'nik' => '3201234567890001',
                'nama' => 'Budi Santoso',
                'whatsapp' => '081234567891',
                'email' => 'budi.santoso@example.com',
                'password' => 'Budi@2026',
            ],
            [
                'nik' => '3201234567890002',
                'nama' => 'Siti Rahayu',
                'whatsapp' => '081234567892',
                'email' => 'siti.rahayu@example.com',
                'password' => 'Siti@2026',
            ],
            [
                'nik' => '3201234567890003',
                'nama' => 'Ahmad Wijaya',
                'whatsapp' => '081234567893',
                'email' => 'ahmad.wijaya@example.com',
                'password' => 'Ahmad@2026',
            ],
            [
                'nik' => '3201234567890004',
                'nama' => 'Dewi Lestari',
                'whatsapp' => '081234567894',
                'email' => 'dewi.lestari@example.com',
                'password' => 'Dewi@2026',
            ],
            [
                'nik' => '3201234567890005',
                'nama' => 'Rudi Hermawan',
                'whatsapp' => '081234567895',
                'email' => 'rudi.hermawan@example.com',
                'password' => 'Rudi@2026',
            ],
        ];

        foreach ($sampleData as $data) {
            DataEntry::updateOrCreate(
                [
                    'website_id' => $website->id,
                    'identifier' => $data['nik'],
                ],
                [
                    'data' => $data,
                    'status' => 'pending',
                    'priority' => 0,
                ]
            );
        }

        $this->command->info("Created " . count($sampleData) . " sample data entries");

        // Generate additional random data entries
        $additionalCount = 20;
        
        for ($i = 0; $i < $additionalCount; $i++) {
            $nikPrefix = $faker->randomElement(['3201', '3202', '3203', '3204', '3205']);
            $nik = $nikPrefix . $faker->numerify('############');
            
            $nama = $faker->name;
            $email = strtolower(str_replace(' ', '.', $nama)) . '@example.com';
            $email = preg_replace('/[^a-z0-9.@]/', '', $email);
            
            $data = [
                'nik' => $nik,
                'nama' => $nama,
                'whatsapp' => '08' . $faker->numerify('##########'),
                'email' => $email,
                'password' => $faker->password(8, 12) . $faker->randomNumber(2),
            ];

            DataEntry::updateOrCreate(
                [
                    'website_id' => $website->id,
                    'identifier' => $nik,
                ],
                [
                    'data' => $data,
                    'status' => 'pending',
                    'priority' => $faker->numberBetween(0, 10),
                ]
            );
        }

        $this->command->info("Generated {$additionalCount} additional random data entries");

        $totalCount = DataEntry::where('website_id', $website->id)->count();
        $this->command->newLine();
        $this->command->info(" Total data entries for {$website->name}: {$totalCount}");
    }
}
