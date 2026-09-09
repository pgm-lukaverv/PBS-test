<?php

use App\Services\CurrencyConversionService;
use Illuminate\Support\Facades\Http;

test('returns the amount unchanged when the currency is EUR', function () {
    // No HTTP call should ever happen for EUR — assert none was made.
    Http::fake();

    $result = app(CurrencyConversionService::class)->convertToEur(100.0, 'EUR');

    expect($result)->toBe(100.0);
    Http::assertNothingSent();
});

test('converts USD to EUR using the live rate', function () {
    Http::fake([
        'openexchangerates.org/*' => Http::response([
            'base' => 'USD',
            'rates' => ['USD' => 1, 'EUR' => 0.9, 'GBP' => 0.8],
        ]),
    ]);

    $result = app(CurrencyConversionService::class)->convertToEur(100.0, 'USD');

    // 100 USD * 0.9 EUR/USD = 90.00 EUR
    expect($result)->toBe(90.0);
});

test('converts GBP to EUR via a USD cross-rate', function () {
    Http::fake([
        'openexchangerates.org/*' => Http::response([
            'base' => 'USD',
            'rates' => ['USD' => 1, 'EUR' => 0.9, 'GBP' => 0.8],
        ]),
    ]);

    $result = app(CurrencyConversionService::class)->convertToEur(100.0, 'GBP');

    // 100 GBP / 0.8 (GBP per USD) = 125 USD, then 125 * 0.9 = 112.50 EUR
    expect($result)->toBe(112.5);
});

test('caches exchange rates so multiple conversions only trigger one API call', function () {
    Http::fake([
        'openexchangerates.org/*' => Http::response([
            'base' => 'USD',
            'rates' => ['USD' => 1, 'EUR' => 0.9, 'GBP' => 0.8],
        ]),
    ]);

    $service = app(CurrencyConversionService::class);
    $service->convertToEur(100.0, 'USD');
    $service->convertToEur(50.0, 'GBP');

    Http::assertSentCount(1);
});

test('returns null when the exchange rate API is unavailable', function () {
    Http::fake([
        'openexchangerates.org/*' => Http::response(null, 500),
    ]);

    $result = app(CurrencyConversionService::class)->convertToEur(100.0, 'USD');

    expect($result)->toBeNull();
});

test('returns null when the rates response is missing the requested currency', function () {
    Http::fake([
        'openexchangerates.org/*' => Http::response([
            'base' => 'USD',
            'rates' => ['USD' => 1, 'EUR' => 0.9], // no GBP
        ]),
    ]);

    $result = app(CurrencyConversionService::class)->convertToEur(100.0, 'GBP');

    expect($result)->toBeNull();
});
