<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Prices and discounts at the POS.
 *
 * Prices vary from day to day, so a cart line may be sold at any actual price between
 * the product's minimum and (maximum) selling price. Only an admin sets that price:
 * an admin at the till changes it directly; for a cashier an admin enters the price
 * and their password on the cashier's screen, which returns a short-lived signed
 * approval the cart carries until checkout.
 */
class PosPricingService
{
    private const APPROVAL_TTL_MINUTES = 120;

    public function __construct(
        private readonly DiscountPolicyService $discountPolicy,
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
     * An admin approves an actual selling price on a cashier's screen.
     *
     * @return array{token: string, approved_by: string, unit_price: float}
     */
    public function approvePrice(Product $product, float $unitPrice, User $cashier, string $adminPassword): array
    {
        $unitPrice = round($unitPrice, 2);
        $this->assertWithinRange($product, $unitPrice, 'unit_price');

        $admin = $this->adminForPassword($adminPassword);

        if (! $admin) {
            throw ValidationException::withMessages([
                'admin_password' => 'That admin password is not correct.',
            ]);
        }

        return [
            'token' => Crypt::encryptString(json_encode([
                'product_id' => $product->id,
                'unit_price' => $unitPrice,
                'approved_by' => $admin->id,
                'cashier_id' => $cashier->id,
                'expires_at' => now()->addMinutes(self::APPROVAL_TTL_MINUTES)->timestamp,
            ])),
            'approved_by' => $admin->name,
            'unit_price' => $unitPrice,
        ];
    }

    /**
     * Turn the cart into sale lines. Prices and discounts are checked here against the
     * catalogue, the discount setting and any admin approvals; nothing from the cart is
     * trusted as-is. A cart line's old `unit_price` is still ignored, as it always was;
     * a changed price only comes in as `actual_price`.
     *
     * @param  list<array{product_id: int|string, quantity: int|string, actual_price?: mixed, discount_amount?: mixed, price_approval?: mixed}>  $cartItems
     * @return array{items: list<array{product_id: int, quantity: int, discount_amount: float}>, approved_prices: array<int, array{unit_price: float, approved_by: User}>}
     */
    public function saleLines(array $cartItems, float $saleDiscount, User $user): array
    {
        $mode = $this->systemConfiguration->discountMode();

        if ($saleDiscount > 0 && $mode === 'item') {
            throw ValidationException::withMessages([
                'discount_amount' => 'Discounts are given per item in the cart, not on the whole sale.',
            ]);
        }

        /** @var Collection<int, Product> $products */
        $products = Product::query()
            ->whereIn('id', collect($cartItems)->pluck('product_id'))
            ->get()
            ->keyBy('id');

        $items = [];
        $approvedPrices = [];

        foreach (array_values($cartItems) as $index => $item) {
            $product = $products->get((int) $item['product_id']);
            $setPrice = round((float) $product->default_selling_price, 2);
            $actualPrice = filled($item['actual_price'] ?? null) ? round((float) $item['actual_price'], 2) : $setPrice;
            $lineDiscount = round((float) ($item['discount_amount'] ?? 0), 2);

            if ($lineDiscount > 0 && $mode === 'sale') {
                throw ValidationException::withMessages([
                    "items.{$index}.discount_amount" => 'Discounts are given on the whole sale, not per item.',
                ]);
            }

            if (abs($actualPrice - $setPrice) > 0.009) {
                $this->assertWithinRange($product, $actualPrice, "items.{$index}.actual_price");

                $approvedPrices[$product->id] = [
                    'unit_price' => $actualPrice,
                    'approved_by' => $this->isAdmin($user)
                        ? $user
                        : $this->approverFor($item['price_approval'] ?? null, $product, $actualPrice, $user, $index),
                ];
            }

            $items[] = [
                'product_id' => $product->id,
                'quantity' => (int) $item['quantity'],
                'discount_amount' => $lineDiscount,
            ];
        }

        return ['items' => $items, 'approved_prices' => $approvedPrices];
    }

    private function assertWithinRange(Product $product, float $unitPrice, string $errorKey): void
    {
        ['minimum' => $minimum, 'maximum' => $maximum] = $this->discountPolicy->priceRange($product);

        if ($unitPrice < $minimum - 0.009 || $unitPrice > $maximum + 0.009) {
            throw ValidationException::withMessages([
                $errorKey => "The price for {$product->product_name} must be between "
                    .number_format($minimum, 2).' and '.number_format($maximum, 2).'.',
            ]);
        }
    }

    private function approverFor(mixed $token, Product $product, float $unitPrice, User $cashier, int $index): User
    {
        $error = ValidationException::withMessages([
            "items.{$index}.actual_price" => "Admin approval is needed to sell {$product->product_name} at a changed price.",
        ]);

        if (! is_string($token) || $token === '') {
            throw $error;
        }

        try {
            $approval = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw $error;
        }

        $valid = (int) ($approval['product_id'] ?? 0) === $product->id
            && abs((float) ($approval['unit_price'] ?? -1) - $unitPrice) <= 0.009
            && (int) ($approval['cashier_id'] ?? 0) === $cashier->id
            && (int) ($approval['expires_at'] ?? 0) >= now()->timestamp;

        $admin = $valid ? User::query()->active()->find((int) ($approval['approved_by'] ?? 0)) : null;

        if (! $admin || ! $this->isAdmin($admin)) {
            throw $error;
        }

        return $admin;
    }
}
