<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentAccount\StorePaymentAccountRequest;
use App\Http\Requests\PaymentAccount\UpdatePaymentAccountRequest;
use App\Http\Resources\PaymentAccountResource;
use App\Models\PaymentAccount;
use App\Services\PaymentAccountService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentAccountController extends Controller
{
    public function __construct(
        private readonly PaymentAccountService $paymentAccountService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $accounts = $this->paymentAccountService->list($request->query());

        return ApiResponse::success(PaymentAccountResource::collection($accounts), 'Payment accounts retrieved successfully');
    }

    public function store(StorePaymentAccountRequest $request): JsonResponse
    {
        $account = $this->paymentAccountService->create($request->validated());

        return ApiResponse::created(new PaymentAccountResource($account), 'Payment account created successfully');
    }

    public function show(PaymentAccount $paymentAccount): JsonResponse
    {
        return ApiResponse::success(new PaymentAccountResource($paymentAccount), 'Payment account retrieved successfully');
    }

    public function update(UpdatePaymentAccountRequest $request, PaymentAccount $paymentAccount): JsonResponse
    {
        $account = $this->paymentAccountService->update($paymentAccount, $request->validated());

        return ApiResponse::updated(new PaymentAccountResource($account), 'Payment account updated successfully');
    }

    public function destroy(PaymentAccount $paymentAccount): JsonResponse
    {
        $this->paymentAccountService->delete($paymentAccount);

        return ApiResponse::deleted('Payment account deleted successfully');
    }
}
