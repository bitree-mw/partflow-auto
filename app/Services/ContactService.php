<?php

namespace App\Services;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ContactService
{
    public function list(array $filters = []): Collection
    {
        return Contact::query()
            ->search($filters['search'] ?? null)
            ->type($filters['contact_type'] ?? null)
            ->when(isset($filters['is_active']), function ($query) use ($filters) {
                $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
            })
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get();
    }

    public function create(array $data): Contact
    {
        return DB::transaction(function () use ($data) {
            $contact = Contact::create([
                'contact_type' => $data['contact_type'],
                'code' => isset($data['code']) ? strtoupper($data['code']) : null,
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'email' => isset($data['email']) ? strtolower($data['email']) : null,
                'tax_number' => $data['tax_number'] ?? null,
                'credit_limit' => $data['credit_limit'] ?? 0,
                'address' => $data['address'] ?? null,
                'notes' => $data['notes'] ?? null,
                'is_active' => $data['is_active'] ?? true,
            ]);

            if (empty($contact->code)) {
                $contact->forceFill([
                    'code' => $this->generateCode($contact),
                ])->save();
            }

            return $contact->refresh();
        });
    }

    public function update(Contact $contact, array $data): Contact
    {
        if (isset($data['code']) && $data['code'] !== null) {
            $data['code'] = strtoupper($data['code']);
        }

        if (isset($data['email']) && $data['email'] !== null) {
            $data['email'] = strtolower($data['email']);
        }

        $contact->update($data);

        return $contact->refresh();
    }

    public function delete(Contact $contact): void
    {
        $contact->delete();
    }

    private function generateCode(Contact $contact): string
    {
        $prefix = match ($contact->contact_type) {
            'supplier' => 'SUP',
            'both' => 'CON',
            default => 'CUS',
        };

        return $prefix.'-'.str_pad((string) $contact->id, 5, '0', STR_PAD_LEFT);
    }
}
