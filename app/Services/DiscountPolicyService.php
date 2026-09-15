<?php

namespace App\Services;

use App\Models\Product;

class DiscountPolicyService
{
    public const DEFAULT_MAXIMUM_DISCOUNT_PERCENTAGE = 20.0;

    private ?float $cachedMaximumDiscountPercentage = null;

    public function __construct(
        private readonly SystemConfigurationService $systemConfiguration
    ) {}

    public function maximumDiscountPercentage(): float
    {
        return $this->cachedMaximumDiscountPercentage ??= max(
            0,
            min(100, (float) $this->systemConfiguration->settings()['maximum_discount_percentage'])
        );
    }

    public function defaultMinimumSellingPrice(float $sellingPrice): float
    {
        return round(max(0, $sellingPrice) * (1 - (self::DEFAULT_MAXIMUM_DISCOUNT_PERCENTAGE / 100)), 2);
    }

    public function minimumAuthorizedUnitPrice(Product $product): float
    {
        return $this->minimumAuthorizedPrice(
            (float) $product->default_selling_price,
            (float) $product->minimum_selling_price
        );
    }

    public function minimumAuthorizedPrice(float $sellingPrice, float $minimumSellingPrice): float
    {
        $sellingPrice = max(0, $sellingPrice);
        $productMinimum = max(0, min($sellingPrice, $minimumSellingPrice));
        $adminMinimum = round($sellingPrice * (1 - ($this->maximumDiscountPercentage() / 100)), 2);

        return round(max($productMinimum, $adminMinimum), 2);
    }

    public function maximumDiscountPercentageFor(Product $product): float
    {
        return $this->maximumDiscountPercentageForPrices(
            (float) $product->default_selling_price,
            (float) $product->minimum_selling_price
        );
    }

    public function maximumDiscountPercentageForPrices(float $sellingPrice, float $minimumSellingPrice): float
    {
        if ($sellingPrice <= 0) {
            return 0;
        }

        return round((($sellingPrice - $this->minimumAuthorizedPrice($sellingPrice, $minimumSellingPrice)) / $sellingPrice) * 100, 2);
    }
}
