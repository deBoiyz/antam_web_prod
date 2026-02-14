<?php

namespace Database\Seeders;

use App\Models\Website;
use App\Models\DataEntry;
use Illuminate\Database\Seeder;
use Faker\Factory as Faker;

class MockButikDataEntriesSeeder extends Seeder
{
    /**
     * Seed sample data entries for testing the Serpong and Bintaro mock web targets.
     * Data structure matches bot-old format: nama, nik, telepon (phone_number)
     */
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        // Get mock websites
        $serpongWebsite = Website::where('slug', 'mock-antam-serpong')->first();
        $bintaroWebsite = Website::where('slug', 'mock-antam-bintaro')->first();

        if (!$serpongWebsite && !$bintaroWebsite) {
            $this->command->error('Please run MockSerpongWebTargetSeeder and MockBintaroWebTargetSeeder first!');
            return;
        }

        // Sample manual data entries (matching bot-old format)
        $sampleData = [
            [
                'nama' => 'BUDI SANTOSO',
                'nik' => '3175032808880001',
                'telepon' => '081234567890',
            ],
            [
                'nama' => 'SITI RAHAYU',
                'nik' => '3175034509900002',
                'telepon' => '082345678901',
            ],
            [
                'nama' => 'AHMAD HIDAYAT',
                'nik' => '3175031212850003',
                'telepon' => '083456789012',
            ],
            [
                'nama' => 'DEWI LESTARI',
                'nik' => '3175035603920004',
                'telepon' => '084567890123',
            ],
            [
                'nama' => 'RIZKY PRATAMA',
                'nik' => '3175032103950005',
                'telepon' => '085678901234',
            ],
            [
                'nama' => 'NURUL HIDAYAH',
                'nik' => '3175036708880006',
                'telepon' => '086789012345',
            ],
        ];

        // Seed for Serpong
        if ($serpongWebsite) {
            $this->command->info("Seeding data entries for Serpong...");
            
            foreach ($sampleData as $data) {
                DataEntry::updateOrCreate(
                    [
                        'website_id' => $serpongWebsite->id,
                        'identifier' => $data['nik'],
                    ],
                    [
                        'data' => [
                            'nama' => $data['nama'],
                            'nik' => $data['nik'],
                            'telepon' => $data['telepon'],
                            'phone_number' => $data['telepon'], // alias for bot-old compatibility
                            'name' => $data['nama'], // alias for bot-old compatibility
                        ],
                        'status' => 'pending',
                        'priority' => 1,
                    ]
                );
            }

            // Generate additional random entries
            for ($i = 0; $i < 15; $i++) {
                $nik = '3175' . str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT) . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $nama = strtoupper($faker->name);
                $telepon = '08' . rand(1, 9) . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT);

                DataEntry::updateOrCreate(
                    [
                        'website_id' => $serpongWebsite->id,
                        'identifier' => $nik,
                    ],
                    [
                        'data' => [
                            'nama' => $nama,
                            'nik' => $nik,
                            'telepon' => $telepon,
                            'phone_number' => $telepon,
                            'name' => $nama,
                        ],
                        'status' => 'pending',
                        'priority' => rand(1, 3),
                    ]
                );
            }

            $this->command->info("  Created " . (count($sampleData) + 15) . " data entries for Serpong");
        }

        // Seed for Bintaro
        if ($bintaroWebsite) {
            $this->command->info("Seeding data entries for Bintaro...");
            
            foreach ($sampleData as $data) {
                DataEntry::updateOrCreate(
                    [
                        'website_id' => $bintaroWebsite->id,
                        'identifier' => $data['nik'],
                    ],
                    [
                        'data' => [
                            'nama' => $data['nama'],
                            'nik' => $data['nik'],
                            'telepon' => $data['telepon'],
                            'phone_number' => $data['telepon'],
                            'name' => $data['nama'],
                        ],
                        'status' => 'pending',
                        'priority' => 1,
                    ]
                );
            }

            // Generate additional random entries
            for ($i = 0; $i < 15; $i++) {
                $nik = '3174' . str_pad(rand(0, 99999999), 8, '0', STR_PAD_LEFT) . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                $nama = strtoupper($faker->name);
                $telepon = '08' . rand(1, 9) . str_pad(rand(0, 999999999), 9, '0', STR_PAD_LEFT);

                DataEntry::updateOrCreate(
                    [
                        'website_id' => $bintaroWebsite->id,
                        'identifier' => $nik,
                    ],
                    [
                        'data' => [
                            'nama' => $nama,
                            'nik' => $nik,
                            'telepon' => $telepon,
                            'phone_number' => $telepon,
                            'name' => $nama,
                        ],
                        'status' => 'pending',
                        'priority' => rand(1, 3),
                    ]
                );
            }

            $this->command->info(" Created " . (count($sampleData) + 15) . " data entries for Bintaro");
        }

        $this->command->newLine();
    }
}
