<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExpenseCategory\StoreExpenseCategoryRequest;
use App\Http\Requests\ExpenseCategory\UpdateExpenseCategoryRequest;
use App\Http\Resources\ExpenseCategoryResource;
use App\Models\ExpenseCategory;
use App\Services\ExpenseCategoryService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExpenseCategoryController extends Controller
{
    public function __construct(
        private readonly ExpenseCategoryService $expenseCategoryService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $categories = $this->expenseCategoryService->list($request->query());

        return ApiResponse::success(ExpenseCategoryResource::collection($categories), 'Expense categories retrieved successfully');
    }

    public function store(StoreExpenseCategoryRequest $request): JsonResponse
    {
        $category = $this->expenseCategoryService->create($request->validated());

        return ApiResponse::created(new ExpenseCategoryResource($category), 'Expense category created successfully');
    }

    public function show(ExpenseCategory $expenseCategory): JsonResponse
    {
        return ApiResponse::success(new ExpenseCategoryResource($expenseCategory), 'Expense category retrieved successfully');
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): JsonResponse
    {
        $category = $this->expenseCategoryService->update($expenseCategory, $request->validated());

        return ApiResponse::updated(new ExpenseCategoryResource($category), 'Expense category updated successfully');
    }

    public function destroy(ExpenseCategory $expenseCategory): JsonResponse
    {
        $this->expenseCategoryService->delete($expenseCategory);

        return ApiResponse::deleted('Expense category deleted successfully');
    }
}
