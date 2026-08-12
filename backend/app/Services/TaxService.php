<?php

namespace App\Services;

use App\Models\TaxProfile;

class TaxService
{
    protected function resolveProfile(?string $taxProfileId): ?TaxProfile
    {
        if (!empty($taxProfileId)) {
            $profile = TaxProfile::find($taxProfileId);
            if ($profile) {
                return $profile;
            }
        }

        return TaxProfile::where('is_default', true)->where('is_active', true)->first();
    }

    public function calculate(?string $taxProfileId, float $amount): array
    {
        $taxProfile = $this->resolveProfile($taxProfileId);

        if (!$taxProfile) {
            return [
                'tax_amount' => 0.0,
                'tax_profile' => null,
            ];
        }

        $taxAmount = $this->computeTax($taxProfile, $amount);

        return [
            'tax_amount' => round($taxAmount, 2),
            'tax_profile' => $taxProfile,
        ];
    }

    public function calculateItemTax(?string $taxProfileId, float $itemSubtotal): array
    {
        $taxProfile = $this->resolveProfile($taxProfileId);

        if (!$taxProfile) {
            return [
                'tax_amount' => 0.0,
                'tax_rate' => 0,
            ];
        }

        $taxAmount = $this->computeTax($taxProfile, $itemSubtotal);

        return [
            'tax_amount' => round($taxAmount, 2),
            'tax_rate' => $taxProfile->rate,
        ];
    }

    protected function computeTax(TaxProfile $taxProfile, float $amount): float
    {
        if ($taxProfile->type === 'exclusive') {
            return $amount * ($taxProfile->rate / 100);
        }

        $taxAmount = $amount - ($amount / (1 + $taxProfile->rate / 100));

        return round($taxAmount, 2);
    }
}
