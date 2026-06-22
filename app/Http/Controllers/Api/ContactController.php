<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contact\StoreContactRequest;
use App\Http\Requests\Contact\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use App\Services\ContactService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(
        private readonly ContactService $contactService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $contacts = $this->contactService->list($request->query());

        return ApiResponse::success(
            data: ContactResource::collection($contacts),
            message: 'Contacts retrieved successfully'
        );
    }

    public function store(StoreContactRequest $request): JsonResponse
    {
        $contact = $this->contactService->create($request->validated());

        return ApiResponse::created(
            data: new ContactResource($contact),
            message: 'Contact created successfully'
        );
    }

    public function show(Contact $contact): JsonResponse
    {
        return ApiResponse::success(
            data: new ContactResource($contact),
            message: 'Contact retrieved successfully'
        );
    }

    public function update(UpdateContactRequest $request, Contact $contact): JsonResponse
    {
        $contact = $this->contactService->update($contact, $request->validated());

        return ApiResponse::updated(
            data: new ContactResource($contact),
            message: 'Contact updated successfully'
        );
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $this->contactService->delete($contact);

        return ApiResponse::deleted('Contact deleted successfully');
    }
}
