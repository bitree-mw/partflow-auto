<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Expense\UpdateExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Services\ExpenseService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenseService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $expenses = $this->expenseService->list($request->query());

        return ApiResponse::success(ExpenseResource::collection($expenses), 'Expenses retrieved successfully');
    }

    public function store(StoreExpenseRequest $request): JsonResponse
    {
        $expense = $this->expenseService->create($request->validated(), $request->user());

        return ApiResponse::created(new ExpenseResource($expense), 'Expense created successfully');
    }

    public function show(Expense $expense): JsonResponse
    {
        $expense->load(['expenseCategory', 'paymentAccount', 'site', 'creator']);

        return ApiResponse::success(new ExpenseResource($expense), 'Expense retrieved successfully');
    }

    public function update(UpdateExpenseRequest $request, Expense $expense): JsonResponse
    {
        $expense = $this->expenseService->update($expense, $request->validated());

        return ApiResponse::updated(new ExpenseResource($expense), 'Expense updated successfully');
    }

    public function destroy(Expense $expense): JsonResponse
    {
        $this->expenseService->delete($expense);

        return ApiResponse::deleted('Expense deleted successfully');
    }
}
