<?php

use Illuminate\Support\Facades\Route;

// The whole app is a single page: search + order list, rendered by the OrderList Livewire component.
Route::livewire('/', 'pages::order-list');
