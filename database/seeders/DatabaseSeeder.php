<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(ServiceSeeder::class);

        $password = (string) env('ADMIN_PASSWORD');

        if ($password === '' && app()->environment('production')) {
            throw new RuntimeException('Set ADMIN_PASSWORD in the server .env before running the production seeder.');
        }

        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@cucinow.co')],
            [
                'name' => env('ADMIN_NAME', 'CuciNow Administrator'),
                'password' => Hash::make($password !== '' ? $password : 'password'),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
