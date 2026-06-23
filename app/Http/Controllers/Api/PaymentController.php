<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $payments = $this->paymentService->list($request->query());

        return ApiResponse::success(PaymentResource::collection($payments), 'Payments retrieved successfully');
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->create($request->validated(), $request->user());

        return ApiResponse::created(new PaymentResource($payment), 'Payment recorded successfully');
    }

    public function show(Payment $payment): JsonResponse
    {
        $payment->load(['inventoryDocument', 'paymentAccount', 'receiver']);

        return ApiResponse::success(new PaymentResource($payment), 'Payment retrieved successfully');
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $this->paymentService->delete($payment);

        return ApiResponse::deleted('Payment deleted successfully');
    }
}
