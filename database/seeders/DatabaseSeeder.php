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
        // Seed the exact example users/orders given in the test spec, so the baseline dataset
        // it describes is guaranteed to exist (searching "Alice", "Bob" or "Carol" always works),
        // and all 3 supported currencies are always represented — not just probable with random data.
        $alice = User::factory()->create([
            'name' => 'Alice Johnson',
            'email' => 'alice@mail.com',
            'country_code' => 'EUR',
        ]);
        Order::factory()->create([
            'user_id' => $alice->id,
            'amount' => 150.00,
            'ordered_at' => '2024-05-01 10:30:00',
        ]);

        $bob = User::factory()->create([
            'name' => 'Bob Smith',
            'email' => 'bob@mail.com',
            'country_code' => 'USD',
        ]);
        Order::factory()->create([
            'user_id' => $bob->id,
            'amount' => 300.00,
            'ordered_at' => '2025-11-01 15:45:00',
        ]);

        $carol = User::factory()->create([
            'name' => 'Carol Davis',
            'email' => 'carol@mail.com',
            'country_code' => 'GBP',
        ]);
        Order::factory()->create([
            'user_id' => $carol->id,
            'amount' => 450.00,
            'ordered_at' => '2026-12-04 18:05:00',
        ]);

        // Extra, automatically generated users/orders on top of the baseline above (the spec
        // explicitly allows adding more records, "eventueel geautomatiseerd").
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
