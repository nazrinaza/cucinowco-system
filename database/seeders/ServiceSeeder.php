<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['home-cleaning', 'Home Cleaning', 'Pembersihan Rumah', 'Residential', 120, 'A practical one-off or recurring clean for apartments, condominiums and landed homes.'],
            ['office-cleaning', 'Office Cleaning', 'Pembersihan Pejabat', 'Commercial', 280, 'Scheduled or contract cleaning for offices and managed workplaces.'],
            ['grand-hall-cleaning', 'Grand Hall & Event Cleaning', 'Pembersihan Dewan & Acara', 'Commercial', 800, 'Pre-event preparation and post-event reset for halls, venues and shared spaces.'],
            ['carpet-cleaning', 'Carpet Cleaning', 'Cucian Karpet', 'Specialist', 180, 'Machine shampoo cleaning for carpets and selected soft furnishings.'],
            ['deep-initial-cleaning', 'Deep & Initial Cleaning', 'Pembersihan Menyeluruh & Awal', 'Specialist', 350, 'Detailed cleaning before occupancy, after renovation or when a space needs a full reset.'],
            ['disinfection', 'Disinfection Service', 'Perkhidmatan Disinfeksi', 'Specialist', 280, 'Targeted anti-virus and anti-bacteria treatment using suitable equipment and chemicals.'],
            ['floor-care', 'Floor Coating & Polishing', 'Salutan & Penggilapan Lantai', 'Specialist', 450, 'Surface-specific floor care to restore presentation and support easier maintenance.'],
            ['kitchen-hood', 'Kitchen & Hood Cleaning', 'Pembersihan Dapur & Hud', 'Specialist', 500, 'Specialist cleaning for kitchens, hoods and grease-prone work areas.'],
        ];

        foreach ($services as $index => [$code, $name, $nameMs, $category, $price, $description]) {
            Service::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'name_ms' => $nameMs,
                    'category' => $category,
                    'description' => $description,
                    'unit' => 'job',
                    'base_price' => $price,
                    'is_active' => true,
                    'sort_order' => $index + 1,
                    'metadata' => ['pricing_basis' => 'launch_recommendation', 'final_price_requires_confirmation' => true],
                ],
            );
        }
    }
}
