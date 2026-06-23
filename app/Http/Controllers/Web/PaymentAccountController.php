<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\PaymentAccount\StorePaymentAccountRequest;
use App\Http\Requests\Web\PaymentAccount\UpdatePaymentAccountRequest;
use App\Models\PaymentAccount;
use App\Services\PaymentAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PaymentAccountController extends Controller
{
    public function __construct(
        private readonly PaymentAccountService $paymentAccountService
    ) {}

    public function index(Request $request): View
    {
        // List pages paginate directly here so Blade screens stay responsive as records grow.
        $paymentAccounts = PaymentAccount::query()
            ->search($request->query('search'))
            ->type($request->query('account_type'))
            ->when($request->filled('is_active'), function ($query) use ($request) {
                $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
            })
            ->orderBy('account_name')
            ->paginate(15)
            ->withQueryString();

        return view('payment-accounts.index', [
            'title' => 'Payment Accounts',
            'description' => 'Manage cash, bank, mobile money, and card accounts used for payments.',
            'paymentAccounts' => $paymentAccounts,
            'filters' => $request->only(['search', 'account_type', 'is_active']),
            'accountTypes' => $this->accountTypes(),
        ]);
    }

    public function create(): View
    {
        return view('payment-accounts.create', [
            'title' => 'Create Payment Account',
            'description' => 'Add an account that can receive payments or record expenses.',
            'paymentAccount' => new PaymentAccount(['is_active' => true]),
            'accountTypes' => $this->accountTypes(),
        ]);
    }

    public function store(StorePaymentAccountRequest $request): RedirectResponse
    {
        $paymentAccount = $this->paymentAccountService->create($request->validated());

        return redirect()
            ->route('web.payment-accounts.show', $paymentAccount)
            ->with('success', 'Payment account created successfully.');
    }

    public function show(PaymentAccount $paymentAccount): View
    {
        return view('payment-accounts.show', [
            'title' => 'Payment Account Details',
            'description' => 'Review account setup and status.',
            'paymentAccount' => $paymentAccount,
        ]);
    }

    public function edit(PaymentAccount $paymentAccount): View
    {
        return view('payment-accounts.edit', [
            'title' => 'Edit Payment Account',
            'description' => 'Update account details used by payment and expense workflows.',
            'paymentAccount' => $paymentAccount,
            'accountTypes' => $this->accountTypes(),
        ]);
    }

    public function update(UpdatePaymentAccountRequest $request, PaymentAccount $paymentAccount): RedirectResponse
    {
        $paymentAccount = $this->paymentAccountService->update($paymentAccount, $request->validated());

        return redirect()
            ->route('web.payment-accounts.show', $paymentAccount)
            ->with('success', 'Payment account updated successfully.');
    }

    public function destroy(PaymentAccount $paymentAccount): RedirectResponse
    {
        $this->paymentAccountService->delete($paymentAccount);

        return redirect()
            ->route('web.payment-accounts.index')
            ->with('success', 'Payment account deleted successfully.');
    }

    private function accountTypes(): array
    {
        return [
            'cash' => 'Cash',
            'bank' => 'Bank',
            'mobile_money' => 'Mobile Money',
            'card' => 'Card',
        ];
    }
}
