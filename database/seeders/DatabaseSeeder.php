<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Order;
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
        $users = User::factory()->count(20)->create();

        $users->each(function (User $user): void {
            Order::factory()
                ->count(fake()->numberBetween(0, 5))
                ->create(['user_id' => $user->id]);
        });
    }
}
