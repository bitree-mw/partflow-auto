<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ExpenseIndexRequest;
use App\Http\Requests\Web\SaveExpenseRequest;
use App\Http\Requests\Web\StoreExpenseCategoryRequest;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Services\SiteAccessService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// Recording and correcting expenses; there is deliberately no delete until a reversal policy is agreed.
class ExpensesController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly SiteAccessService $siteAccessService
    ) {}

    public function index(ExpenseIndexRequest $request): View
    {
        $filters = $request->filters();

        return view('expenses.index', [
            'title' => 'Expenses',
            'description' => 'Record running costs such as rent, fuel, wages and utilities, by branch and payment account.',
            'expenses' => $this->expenses->paginate($filters, $request->user()),
            'summary' => $this->expenses->summary($filters, $request->user()),
            'filters' => $filters,
            ...$this->expenses->formOptions($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        return view('expenses.form', [
            'title' => 'Record expense',
            'description' => 'Add money paid out for running the business.',
            'expense' => new Expense(['expense_date' => now()]),
            ...$this->expenses->formOptions($request->user()),
        ]);
    }

    public function store(SaveExpenseRequest $request): RedirectResponse
    {
        $this->expenses->create($request->validated(), $request->user());

        return redirect()->route('web.expenses.index')->with('success', 'Expense recorded.');
    }

    public function edit(Request $request, Expense $expense): View
    {
        $this->siteAccessService->authorizeOptionalSite($request->user(), $expense->site_id);

        return view('expenses.form', [
            'title' => 'Edit expense',
            'description' => 'Correct the details of a recorded expense.',
            'expense' => $expense,
            ...$this->expenses->formOptions($request->user()),
        ]);
    }

    public function update(SaveExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->expenses->update($expense, [
            'payment_account_id' => null,
            'site_id' => null,
            'reference' => null,
            ...$request->validated(),
        ], $request->user());

        return redirect()->route('web.expenses.index')->with('success', 'Expense updated.');
    }

    public function storeCategory(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $category = $this->expenses->createCategory($request->validated());

        return redirect()->back()->with('success', "Category {$category->name} added.");
    }
}
