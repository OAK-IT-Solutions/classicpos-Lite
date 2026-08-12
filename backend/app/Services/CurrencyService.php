<?php

namespace App\Services;

use App\Models\Landlord\Currency;

class CurrencyService
{
    public function getAll(): array
    {
        return Currency::where('is_active', true)->orderBy('is_default', 'desc')->get()->toArray();
    }

    public function getDefault(): ?Currency
    {
        return Currency::where('is_default', true)->where('is_active', true)->first()
            ?? Currency::where('code', 'USD')->first();
    }

    public function get(string $code): ?Currency
    {
        return Currency::where('code', $code)->where('is_active', true)->first();
    }

    public function convert(float $amount, string $fromCurrency, string $toCurrency): float
    {
        $from = Currency::where('code', $fromCurrency)->first();
        $to = Currency::where('code', $toCurrency)->first();

        if (!$from || !$to) return $amount;

        $amountInUsd = $amount / (float) $from->exchange_rate_to_usd;
        return round($amountInUsd * (float) $to->exchange_rate_to_usd, $to->decimal_places ?? 2);
    }

    public function format(float $amount, string $currencyCode): string
    {
        $currency = Currency::where('code', $currencyCode)->first();
        if (!$currency) return number_format($amount, 2);

        return $currency->symbol . number_format($amount, $currency->decimal_places ?? 2);
    }
}
