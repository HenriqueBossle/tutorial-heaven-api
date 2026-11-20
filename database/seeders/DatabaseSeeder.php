<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $this->call(TagSeeder::class);

        User::factory()->create([
            'name' => config('app.test_user.name'),
            'email' => config('app.test_user.email'),
            'password' => config('app.test_user.password'),
            'role' => config('app.test_user.role'),
        ]);
    }
}
