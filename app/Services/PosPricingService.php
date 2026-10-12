<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Prices and discounts at the POS.
 *
 * Every cart line sells at the product's set selling price; the price itself is never changed
 * at the till. Money off is given only as a discount, where the discount setting allows it
 * (none, on the whole sale, per item, or both), and the discount policy caps how much.
 */
class PosPricingService
{
    public function __construct(
        private readonly SystemConfigurationService $systemConfiguration
    ) {}

    public function isAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $user->loadMissing('role');
        $permissions = $user->role?->permissions ?? [];

        return in_array('*', $permissions, true)
            || str($user->role?->name ?? '')->lower()->contains('admin');
    }

    /**
     * The active admin whose password this is, if any.
     */
    public function adminForPassword(string $password): ?User
    {
        if ($password === '') {
            return null;
        }

        return User::query()
            ->active()
            ->whereHas('role', function ($query): void {
                $query->whereJsonContains('permissions', '*')
                    ->orWhere('name', 'like', '%Admin%');
            })
            ->get()
            ->first(fn (User $user): bool => Hash::check($password, $user->password));
    }

    /**
     * Turn the cart into sale lines. Nothing from the cart is trusted as-is: the selling price
     * always comes from the catalogue, and discounts are checked against the discount setting here
     * and against the discount policy when the sale is created.
     *
     * @param  list<array{product_id: int|string, quantity: int|string, actual_price?: mixed, discount_amount?: mixed}>  $cartItems
     * @return list<array{product_id: int, quantity: int, discount_amount: float}>
     */
    public function saleLines(array $cartItems, float $saleDiscount): array
    {
        $mode = $this->systemConfiguration->discountMode();

        if ($saleDiscount > 0 && ! in_array($mode, ['sale', 'both'], true)) {
            throw ValidationException::withMessages([
                'discount_amount' => $mode === 'none'
                    ? 'Discounts are turned off.'
                    : 'Discounts are given per item in the cart, not on the whole sale.',
            ]);
        }

        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->whereIn('id', collect($cartItems)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $items = [];

        foreach (array_values($cartItems) as $index => $item) {
            $product = $products->get((int) $item['product_id']);
            $lineDiscount = round((float) ($item['discount_amount'] ?? 0), 2);

            // Older carts may still send an actual price; only the set price is accepted.
            if (filled($item['actual_price'] ?? null)
                && abs(round((float) $item['actual_price'], 2) - round((float) $product->default_selling_price, 2)) > 0.009) {
                throw ValidationException::withMessages([
                    "items.{$index}.actual_price" => "The selling price of {$product->product_name} cannot be changed at the POS. Give a discount instead.",
                ]);
            }

            if ($lineDiscount > 0 && ! in_array($mode, ['item', 'both'], true)) {
                throw ValidationException::withMessages([
                    "items.{$index}.discount_amount" => $mode === 'none'
                        ? 'Discounts are turned off.'
                        : 'Discounts are given on the whole sale, not per item.',
                ]);
            }

            $items[] = [
                'product_id' => $product->id,
                'quantity' => (int) $item['quantity'],
                'discount_amount' => $lineDiscount,
            ];
        }

        return $items;
    }
}
