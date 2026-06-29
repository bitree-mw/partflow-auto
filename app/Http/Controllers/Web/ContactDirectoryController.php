<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Services\ContactService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ContactDirectoryController extends Controller
{
    public function __construct(
        private readonly ContactService $contactService
    ) {}

    public function customers(): View
    {
        $contacts = $this->contactService->list()->filter->isCustomer()->values();

        return view('contacts.index', [
            'title' => 'Customers',
            'description' => 'Manage customers, credit sales, balances, and buying performance.',
            'mode' => 'customers',
            'createRoute' => route('web.customers.create'),
            'contacts' => $this->contactRows($contacts, 'sale'),
            'analytics' => $this->customerAnalytics($contacts),
        ]);
    }

    public function suppliers(): View
    {
        $contacts = $this->contactService->list()->filter->isSupplier()->values();

        return view('contacts.index', [
            'title' => 'Suppliers',
            'description' => 'Manage suppliers, purchase activity, payable balances, and supply performance.',
            'mode' => 'suppliers',
            'createRoute' => route('web.suppliers.create'),
            'contacts' => $this->contactRows($contacts, 'purchase'),
            'analytics' => $this->supplierAnalytics($contacts),
        ]);
    }

    public function createCustomer(): View
    {
        return $this->contactForm('customers', 'Add Customer', 'Create a customer account for cash or credit sales.', route('web.customers.store'));
    }

    public function createSupplier(): View
    {
        return $this->contactForm('suppliers', 'Add Supplier', 'Create a supplier account for purchases and payable tracking.', route('web.suppliers.store'));
    }

    public function storeCustomer(Request $request): RedirectResponse
    {
        $this->contactService->create($this->validatedContact($request, 'customer'));

        return redirect()
            ->route('web.customers.index')
            ->with('success', 'Customer created successfully.');
    }

    public function storeSupplier(Request $request): RedirectResponse
    {
        $this->contactService->create($this->validatedContact($request, 'supplier'));

        return redirect()
            ->route('web.suppliers.index')
            ->with('success', 'Supplier created successfully.');
    }

    public function destroyCustomer(Contact $contact): RedirectResponse
    {
        abort_unless($contact->isCustomer(), 404);

        return $this->deactivateContact($contact, 'sale', 'web.customers.index', 'Customer');
    }

    public function destroySupplier(Contact $contact): RedirectResponse
    {
        abort_unless($contact->isSupplier(), 404);

        return $this->deactivateContact($contact, 'purchase', 'web.suppliers.index', 'Supplier');
    }

    private function contactForm(string $mode, string $title, string $description, string $action): View
    {
        return view('contacts.create', compact('mode', 'title', 'description', 'action'));
    }

    private function validatedContact(Request $request, string $type): array
    {
        $validated = $request->validate([
            'code' => ['nullable', 'string', 'max:50', 'unique:contacts,code'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
            'address' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ]);

        return $validated + ['contact_type' => $type, 'is_active' => true];
    }

    private function contactRows(Collection $contacts, string $documentType): array
    {
        return $contacts
            ->load(['inventoryDocuments' => fn ($query) => $query->where('document_type', $documentType)])
            ->map(function (Contact $contact) use ($documentType): array {
                $documents = $contact->inventoryDocuments;
                $total = (float) $documents->sum('total_amount');
                $paid = (float) $documents->sum('paid_amount');
                $balance = (float) $documents->sum('balance_amount');
                $paidPercent = $total > 0 ? round(($paid / $total) * 100) : 0;
                $noun = $documentType === 'sale' ? 'sales' : 'purchases';

                return [
                    'id' => $contact->id,
                    'code' => $contact->code,
                    'name' => $contact->name,
                    'phone' => $contact->phone ?: 'N/A',
                    'email' => $contact->email ?: 'N/A',
                    'credit_limit' => $this->money((float) $contact->credit_limit),
                    'balance_amount' => $balance,
                    'balance' => $this->money($balance),
                    'performance' => $documents->count()." {$noun} - {$paidPercent}% paid",
                    'is_active' => (bool) $contact->is_active,
                    'status' => $contact->is_active ? 'Active' : 'Inactive',
                ];
            })
            ->all();
    }

    private function deactivateContact(Contact $contact, string $documentType, string $routeName, string $label): RedirectResponse
    {
        $balance = (float) $contact->inventoryDocuments()
            ->where('document_type', $documentType)
            ->sum('balance_amount');

        if ($balance > 0) {
            return redirect()
                ->route($routeName)
                ->with('error', "{$label} has an outstanding balance and cannot be made inactive.");
        }

        $this->contactService->update($contact, ['is_active' => false]);

        return redirect()
            ->route($routeName)
            ->with('success', "{$label} marked inactive.");
    }

    private function customerAnalytics(Collection $contacts): array
    {
        $documents = $contacts->load('inventoryDocuments')->pluck('inventoryDocuments')->flatten();
        $sales = $documents->where('document_type', 'sale');
        $total = (float) $sales->sum('total_amount');
        $paid = (float) $sales->sum('paid_amount');
        $balance = (float) $sales->sum('balance_amount');

        return [
            ['label' => 'Credit outstanding', 'value' => $this->money($balance), 'detail' => 'Across active customer accounts', 'tone' => $balance > 0 ? 'warning' : 'success'],
            ['label' => 'Credit sales', 'value' => $this->money($total), 'detail' => 'All recorded sales', 'tone' => 'neutral'],
            ['label' => 'Collections', 'value' => $this->money($paid), 'detail' => ($total > 0 ? round(($paid / $total) * 100) : 0).'% collected', 'tone' => 'success'],
            ['label' => 'Open accounts', 'value' => (string) $sales->where('balance_amount', '>', 0)->pluck('contact_id')->unique()->count(), 'detail' => 'Require follow-up', 'tone' => 'danger'],
        ];
    }

    private function supplierAnalytics(Collection $contacts): array
    {
        $documents = $contacts->load('inventoryDocuments')->pluck('inventoryDocuments')->flatten();
        $purchases = $documents->where('document_type', 'purchase');
        $payable = (float) $purchases->sum('balance_amount');
        $returns = $documents->where('document_type', 'purchase_return');

        return [
            ['label' => 'Purchases', 'value' => $this->money((float) $purchases->sum('total_amount')), 'detail' => 'All recorded purchases', 'tone' => 'neutral'],
            ['label' => 'Supplier payable', 'value' => $this->money($payable), 'detail' => 'Outstanding purchases', 'tone' => $payable > 0 ? 'warning' : 'success'],
            ['label' => 'Returns pending', 'value' => $this->money((float) $returns->whereIn('status', ['draft', 'pending'])->sum('total_amount')), 'detail' => 'Awaiting supplier approval', 'tone' => 'danger'],
            ['label' => 'Active suppliers', 'value' => (string) $contacts->count(), 'detail' => 'Available for purchasing', 'tone' => 'success'],
        ];
    }

    private function money(float $amount): string
    {
        return config('services.partflow.base_currency', 'MWK').' '.number_format($amount, 0);
    }
}
