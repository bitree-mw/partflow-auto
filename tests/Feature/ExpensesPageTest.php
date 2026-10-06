<?php

namespace Tests\Feature;

use App\Models\BusinessSetting;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\PaymentAccount;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\UserSiteAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpensesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_records_lists_and_edits_expenses(): void
    {
        $this->usePackage('drive');
        $admin = $this->administrator();
        $site = $this->site('Lilongwe Branch', 'LLBR');
        $account = PaymentAccount::query()->create(['account_name' => 'Main Cash', 'account_type' => 'cash', 'is_active' => true]);

        $this->actingAs($admin)->get(route('web.expenses.index'))
            ->assertOk()
            ->assertSee('Add a category such as Rent');

        $this->post(route('web.expense-categories.store'), ['name' => 'Rent'])->assertSessionHas('success');
        $category = ExpenseCategory::query()->where('name', 'Rent')->sole();
        $this->post(route('web.expense-categories.store'), ['name' => 'Rent'])->assertSessionHasErrors('name');

        $this->post(route('web.expenses.store'), [
            'expense_category_id' => $category->id,
            'payment_account_id' => $account->id,
            'site_id' => $site->id,
            'expense_date' => now()->subHour()->format('Y-m-d\TH:i'),
            'amount' => '150000',
            'description' => 'October shop rent',
            'reference' => 'RCPT-77',
        ])->assertRedirect(route('web.expenses.index'));

        $expense = Expense::query()->sole();
        $this->assertSame($admin->id, $expense->created_by);
        $this->assertSame('150000.00', $expense->amount);

        // Business-wide expenses (no branch) are allowed for administrators.
        $this->post(route('web.expenses.store'), [
            'expense_category_id' => $category->id,
            'expense_date' => now()->subHour()->format('Y-m-d\TH:i'),
            'amount' => '50000',
            'description' => 'Accountant fee',
        ])->assertRedirect(route('web.expenses.index'));

        $this->get(route('web.expenses.index'))
            ->assertOk()
            ->assertSee('October shop rent')
            ->assertSee('Whole business')
            ->assertSee('MWK 200,000.00');

        $this->put(route('web.expenses.update', $expense), [
            'expense_category_id' => $category->id,
            'site_id' => $site->id,
            'expense_date' => now()->subHour()->format('Y-m-d\TH:i'),
            'amount' => '160000',
            'description' => 'October shop rent (corrected)',
        ])->assertRedirect(route('web.expenses.index'));

        $expense->refresh();
        $this->assertSame('160000.00', $expense->amount);
        $this->assertNull($expense->payment_account_id);
        $this->assertNull($expense->reference);

        $this->get(route('web.expenses.index', ['site_id' => $site->id]))
            ->assertOk()
            ->assertSee('MWK 160,000.00')
            ->assertDontSee('Accountant fee');
    }

    public function test_expense_validation_rejects_future_dates_and_inactive_categories(): void
    {
        $this->usePackage('drive');
        $admin = $this->administrator();
        $inactive = ExpenseCategory::query()->create(['name' => 'Old', 'is_active' => false]);
        $active = ExpenseCategory::query()->create(['name' => 'Fuel', 'is_active' => true]);

        $this->actingAs($admin)->post(route('web.expenses.store'), [
            'expense_category_id' => $inactive->id,
            'expense_date' => now()->addDay()->format('Y-m-d\TH:i'),
            'amount' => '0',
            'description' => '',
        ])->assertSessionHasErrors(['expense_category_id', 'expense_date', 'amount', 'description']);

        $this->assertDatabaseCount('expenses', 0);
        $this->assertNotNull($active);
    }

    public function test_branch_staff_can_only_record_expenses_for_their_branch(): void
    {
        $this->usePackage('drive');
        $site = $this->site('Blantyre Branch', 'BTBR');
        $other = $this->site('Zomba Branch', 'ZMBR');
        $role = Role::query()->create(['name' => 'Branch Manager', 'permissions' => ['purchases.*'], 'is_active' => true]);
        $manager = User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
        UserSiteAccess::query()->create(['user_id' => $manager->id, 'site_id' => $site->id, 'access_level' => 'manager', 'is_default' => true, 'is_active' => true]);
        $category = ExpenseCategory::query()->create(['name' => 'Fuel', 'is_active' => true]);
        $payload = fn (?int $siteId): array => [
            'expense_category_id' => $category->id,
            'site_id' => $siteId,
            'expense_date' => now()->subHour()->format('Y-m-d\TH:i'),
            'amount' => '20000',
            'description' => 'Delivery fuel',
        ];

        $this->actingAs($manager)->post(route('web.expenses.store'), $payload(null))->assertForbidden();
        $this->actingAs($manager)->post(route('web.expenses.store'), $payload($other->id))->assertForbidden();
        $this->actingAs($manager)->post(route('web.expenses.store'), $payload($site->id))->assertRedirect(route('web.expenses.index'));
        $this->assertDatabaseCount('expenses', 1);
    }

    public function test_expenses_are_hidden_and_blocked_on_ignition(): void
    {
        $this->usePackage('ignition');
        $admin = $this->administrator();
        $this->site('Only Branch', 'ONLY');

        $this->actingAs($admin)->get(route('web.expenses.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('web.dashboard'))
            ->assertOk()
            ->assertDontSee(route('web.expenses.index'), false)
            ->assertDontSee('Month expenses');
    }

    public function test_dashboard_shows_month_expenses_and_single_branch_refinements(): void
    {
        $admin = $this->administrator();
        $site = $this->site('Only Branch', 'ONLY');
        $category = ExpenseCategory::query()->create(['name' => 'Rent', 'is_active' => true]);
        Expense::query()->create([
            'expense_category_id' => $category->id,
            'site_id' => $site->id,
            'expense_date' => now(),
            'amount' => 75000,
            'description' => 'Rent',
            'created_by' => $admin->id,
        ]);

        $this->usePackage('drive');
        $this->actingAs($admin)->get(route('web.dashboard'))
            ->assertOk()
            ->assertSee('Month expenses')
            ->assertSee('Sales and profit by branch')
            ->assertSee('Debtors and creditors');

        $this->usePackage('ignition');
        $this->actingAs($admin)->get(route('web.dashboard'))
            ->assertOk()
            ->assertDontSee('Month expenses')
            ->assertDontSee('Sales and profit by branch')
            ->assertDontSee('Sales distribution')
            ->assertDontSee('dashboard_site_id', false)
            ->assertSee('Supplier balances')
            ->assertSee('At current selling prices');
    }

    private function usePackage(string $key): void
    {
        BusinessSetting::query()->updateOrCreate(['key' => 'subscription_package'], ['value' => $key]);
        app('request')->attributes->remove('partflow.package');
    }

    private function administrator(): User
    {
        $role = Role::query()->firstOrCreate(['name' => 'System Administrator'], ['permissions' => ['*'], 'is_active' => true]);

        return User::factory()->create(['role_id' => $role->id, 'is_active' => true]);
    }

    private function site(string $name, string $code): Site
    {
        return Site::query()->create(['name' => $name, 'code' => $code, 'type' => 'branch', 'is_active' => true]);
    }
}
