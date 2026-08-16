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
        $payments = $this->paymentService->list($request->query(), $request->user());

        return ApiResponse::success(PaymentResource::collection($payments), 'Payments retrieved successfully');
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        $payment = $this->paymentService->create($request->validated(), $request->user());

        return ApiResponse::created(new PaymentResource($payment), 'Payment recorded successfully');
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        app(\App\Services\SiteAccessService::class)->authorizeInventoryDocument($request->user(), $payment->inventoryDocument);

        $payment->load(['inventoryDocument', 'paymentAccount', 'receiver']);

        return ApiResponse::success(new PaymentResource($payment), 'Payment retrieved successfully');
    }

    public function destroy(Request $request, Payment $payment): JsonResponse
    {
        $this->paymentService->delete($payment, $request->user());

        return ApiResponse::deleted('Payment deleted successfully');
    }
}
