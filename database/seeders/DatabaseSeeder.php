<?php

namespace Database\Seeders;

use App\Models\Order;
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
        // Create a batch of users with random currencies (EUR/USD/GBP, see UserFactory).
        $users = User::factory()->count(20)->create();

        // Attach 0-5 orders to each user. Some users deliberately end up with 0 orders,
        // which is needed to actually exercise the "only show users with orders" search rule.
        $users->each(function (User $user): void {
            Order::factory()
                ->count(fake()->numberBetween(0, 5))
                ->create(['user_id' => $user->id]);
        });
    }
}
