<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CurrencyConversionService
{
    /**
     * How long to cache the fetched exchange rates, in seconds.
     * Free-tier OpenExchangeRates updates hourly, so caching for an hour avoids burning the request quota.
     */
    private const CACHE_TTL = 3600;

    /**
     * Convert an amount from a given currency into EUR, using live rates.
     *
     * The free OpenExchangeRates plan only returns rates with USD as the base, so:
     * - USD -> EUR is a direct multiplication.
     * - GBP -> EUR (or any non-USD currency) is done via a USD cross-rate:
     *   amount_in_usd = amount / rate(GBP), then amount_in_usd * rate(EUR).
     */
    public function convertToEur(float $amount, string $fromCurrency): ?float
    {
        $fromCurrency = strtoupper($fromCurrency);

        if ($fromCurrency === 'EUR') {
            return round($amount, 2);
        }

        $rates = $this->getRates();

        if ($rates === null) {
            // API unavailable — log and let the caller decide how to handle a null result.
            Log::warning('CurrencyConversionService: exchange rates unavailable, cannot convert.', [
                'from_currency' => $fromCurrency,
            ]);

            return null;
        }

        if (! isset($rates[$fromCurrency]) || ! isset($rates['EUR'])) {
            Log::warning('CurrencyConversionService: missing rate for currency.', [
                'from_currency' => $fromCurrency,
            ]);

            return null;
        }

        // rates are USD-based: 1 USD = rates[$fromCurrency] of that currency.
        // amount in USD = amount / rates[$fromCurrency]
        // amount in EUR = amount_in_usd * rates['EUR']
        $amountInUsd = $amount / $rates[$fromCurrency];
        $amountInEur = $amountInUsd * $rates['EUR'];

        return round($amountInEur, 2);
    }

    /**
     * Fetch (and cache) the latest USD-based exchange rates from OpenExchangeRates.
     *
     * @return array<string, float>|null
     */
    private function getRates(): ?array
    {
        return Cache::remember('openexchangerates.latest_rates', self::CACHE_TTL, function (): ?array {
            $response = Http::get('https://openexchangerates.org/api/latest.json', [
                'app_id' => config('services.openexchangerates.key'),
            ]);

            if ($response->failed()) {
                Log::error('CurrencyConversionService: failed to fetch exchange rates.', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $response->json('rates');
        });
    }
}
