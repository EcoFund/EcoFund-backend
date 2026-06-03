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
        $this->call([
            // UserSeeder::class, // if there is no user seeder, let's keep it if it was there or maybe we should add KategoriSeeder here. 
            // Wait, I will just add KategoriSeeder::class
            KategoriSeeder::class,
        ]);
    }
}
