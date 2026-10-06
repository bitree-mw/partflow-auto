<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\PaymentAccount\StorePaymentAccountRequest;
use App\Http\Requests\Web\PaymentAccount\UpdatePaymentAccountRequest;
use App\Models\PaymentAccount;
use App\Support\CollectionPaginator;
use App\Support\ReturnUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Validation\ValidationException;

class PaymentAccountController extends Controller
{
    public function index(Request $request): View
    {
        $accounts = $this->apiCollection($request, 'GET', 'payment-accounts', $request->only(['search', 'account_type', 'is_active']));

        return view('payment-accounts.index', [
            'title' => 'Payment Accounts',
            'description' => 'Manage cash, bank, mobile money, and card accounts used for payments.',
            'paymentAccounts' => CollectionPaginator::paginate($accounts, $request),
            'filters' => $request->only(['search', 'account_type', 'is_active']),
            'accountTypes' => $this->accountTypes(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('payment-accounts.create', [
            'returnTo' => ReturnUrl::from($request),
            'title' => 'Create Payment Account',
            'description' => 'Add an account that can receive payments or record expenses.',
            'paymentAccount' => new PaymentAccount(['is_active' => true]),
            'accountTypes' => $this->accountTypes(),
        ]);
    }

    public function store(StorePaymentAccountRequest $request): RedirectResponse
    {
        $response = $this->apiCall($request, 'POST', 'payment-accounts', $request->validated());
        $paymentAccount = $this->hydratePaymentAccount($response['data'] ?? []);

        return redirect()
            ->to(ReturnUrl::from($request) ?? route('web.payment-accounts.show', $paymentAccount))
            ->with('success', $response['message'] ?? 'Payment account created successfully.');
    }

    public function show(Request $request, PaymentAccount $paymentAccount): View
    {
        $paymentAccount = $this->apiAccount($request, $paymentAccount);

        return view('payment-accounts.show', [
            'returnTo' => ReturnUrl::from($request),
            'title' => 'Payment Account Details',
            'description' => 'Review account setup and status.',
            'paymentAccount' => $paymentAccount,
        ]);
    }

    public function edit(Request $request, PaymentAccount $paymentAccount): View
    {
        $paymentAccount = $this->apiAccount($request, $paymentAccount);

        return view('payment-accounts.edit', [
            'returnTo' => ReturnUrl::from($request),
            'title' => 'Edit Payment Account',
            'description' => 'Update account details used by payment and expense workflows.',
            'paymentAccount' => $paymentAccount,
            'accountTypes' => $this->accountTypes(),
        ]);
    }

    public function update(UpdatePaymentAccountRequest $request, PaymentAccount $paymentAccount): RedirectResponse
    {
        $response = $this->apiCall($request, 'PUT', "payment-accounts/{$paymentAccount->getKey()}", $request->validated());
        $paymentAccount = $this->hydratePaymentAccount($response['data'] ?? []);

        return redirect()
            ->to(ReturnUrl::from($request) ?? route('web.payment-accounts.show', $paymentAccount))
            ->with('success', $response['message'] ?? 'Payment account updated successfully.');
    }

    public function destroy(Request $request, PaymentAccount $paymentAccount): RedirectResponse
    {
        $response = $this->apiCall($request, 'DELETE', "payment-accounts/{$paymentAccount->getKey()}");

        return redirect()
            ->route('web.payment-accounts.index')
            ->with('success', $response['message'] ?? 'Payment account deleted successfully.');
    }

    private function apiAccount(Request $request, PaymentAccount $paymentAccount): PaymentAccount
    {
        $response = $this->apiCall($request, 'GET', "payment-accounts/{$paymentAccount->getKey()}");

        return $this->hydratePaymentAccount($response['data'] ?? []);
    }

    private function apiCollection(Request $request, string $method, string $endpoint, array $payload = []): Collection
    {
        $response = $this->apiCall($request, $method, $endpoint, $payload);

        return collect($response['data'] ?? [])
            ->map(fn (array $account): PaymentAccount => $this->hydratePaymentAccount($account))
            ->values();
    }

    private function apiCall(Request $request, string $method, string $endpoint, array $payload = []): array
    {
        $token = $this->sessionApiToken($request);
        $parameters = strtoupper($method) === 'GET' ? $payload : [];
        $requestPayload = strtoupper($method) === 'GET' ? [] : $payload;
        $apiRequest = Request::create("/api/{$endpoint}", $method, $parameters);

        $apiRequest->headers->set('Accept', 'application/json');
        $apiRequest->headers->set('Authorization', "Bearer {$token}");
        $apiRequest->setUserResolver(fn () => $request->user());

        if ($requestPayload !== []) {
            $apiRequest->request->replace($requestPayload);
        }

        $response = RouteFacade::dispatch($apiRequest);
        $body = json_decode($response->getContent(), true) ?: [];

        if ($response->getStatusCode() === 422) {
            throw ValidationException::withMessages($body['errors'] ?? ['payment_account' => $body['message'] ?? 'Payment account validation failed.']);
        }

        if ($response->getStatusCode() >= 400 || ! ($body['success'] ?? false)) {
            abort($response->getStatusCode(), $body['message'] ?? 'Payment account API request failed.');
        }

        return $body;
    }

    private function sessionApiToken(Request $request): string
    {
        $token = $request->session()->get('partflow_api_token');

        if ($token) {
            return $token;
        }

        $user = $request->user();

        abort_unless($user, 401);

        $token = $user->createToken('partflow-web-session')->plainTextToken;
        $request->session()->put('partflow_api_token', $token);
        $request->session()->put('partflow_api_token_id', strtok($token, '|') ?: null);

        return $token;
    }

    private function hydratePaymentAccount(array $data): PaymentAccount
    {
        $paymentAccount = new PaymentAccount;
        $paymentAccount->forceFill($data);
        $paymentAccount->exists = true;

        return $paymentAccount;
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
