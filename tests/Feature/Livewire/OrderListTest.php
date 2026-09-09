<?php

use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

test('renders successfully', function () {
    Livewire::test('pages::order-list')
        ->assertStatus(200);
});

test('only shows users who have at least one order', function () {
    $userWithOrders = User::factory()->create(['name' => 'Alice Johnson']);
    Order::factory()->create(['user_id' => $userWithOrders->id]);

    $userWithoutOrders = User::factory()->create(['name' => 'Bob NoOrders']);

    Livewire::test('pages::order-list')
        ->assertSee('Alice Johnson')
        ->assertDontSee('Bob NoOrders');
});

test('filters orders by the searched username', function () {
    $alice = User::factory()->create(['name' => 'Alice Johnson']);
    Order::factory()->create(['user_id' => $alice->id]);

    $carol = User::factory()->create(['name' => 'Carol Davis']);
    Order::factory()->create(['user_id' => $carol->id]);

    Livewire::test('pages::order-list')
        ->set('search', 'Alice')
        ->assertSee('Alice Johnson')
        ->assertDontSee('Carol Davis');
});

test('paginates orders to 10 per page', function () {
    $user = User::factory()->create();
    Order::factory()->count(15)->create(['user_id' => $user->id]);

    Livewire::test('pages::order-list')
        ->assertCount('orders', 10)
        ->call('nextPage')
        ->assertCount('orders', 5);
});
