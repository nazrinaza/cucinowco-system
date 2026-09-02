<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['office-cleaning', 'Office Cleaning', null, 'Commercial', 280, 'Scheduled or contract cleaning for offices and managed workplaces.'],
            ['grand-hall-cleaning', 'Grand Hall & Event Cleaning', null, 'Commercial', 800, 'Pre-event preparation and post-event reset for halls, venues and shared spaces.'],
            ['carpet-cleaning', 'Carpet Cleaning', null, 'Specialist', 180, 'Machine shampoo cleaning for carpets and selected soft furnishings.'],
            ['deep-initial-cleaning', 'Deep & Initial Cleaning', null, 'Specialist', 350, 'Detailed cleaning before occupancy, after renovation or when a space needs a full reset.'],
            ['disinfection', 'Disinfection Service', null, 'Specialist', 280, 'Targeted anti-virus and anti-bacteria treatment using suitable equipment and chemicals.'],
            ['floor-care', 'Floor Coating & Polishing', null, 'Specialist', 450, 'Surface-specific floor care to restore presentation and support easier maintenance.'],
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
