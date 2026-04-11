<?php

namespace App\Services;

use App\Models\ExternalInvoice;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ExternalInvoiceClient
{
    /**
     * @return array<string, mixed>|null
     */
    public function getByUuid(string $uuid): ?array
    {
        $uuid = trim($uuid);
        if ($uuid === '') {
            throw new InvalidArgumentException('uuid is required');
        }

        $invoice = ExternalInvoice::on($this->readConnectionName())
            ->where('uuid', $uuid)
            ->first();

        if ($invoice === null) {
            return null;
        }

        return $this->normalizeInvoice($invoice->toArray());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function getByClosingBillId(int $closingBillId): Collection
    {
        if ($closingBillId <= 0) {
            throw new InvalidArgumentException('closingBillId must be greater than 0');
        }

        return ExternalInvoice::on($this->readConnectionName())
            ->where('closing_bill_id', $closingBillId)
            ->orderBy('id')
            ->get()
            ->map(fn (ExternalInvoice $invoice): array => $this->normalizeInvoice($invoice->toArray()))
            ->values();
    }

    private function readConnectionName(): string
    {
        return is_array(config('database.connections.sakemaru_read'))
            ? 'sakemaru_read'
            : 'sakemaru';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizeInvoice(array $payload): array
    {
        $metadata = $this->normalizeMetadata($payload['metadata'] ?? []);

        return [
            'uuid' => (string) ($payload['uuid'] ?? ''),
            'client_id' => $this->toNullableInt($payload['client_id'] ?? null),
            'closing_bill_id' => $this->toNullableInt($payload['closing_bill_id'] ?? null),
            'partner_id' => $this->toNullableInt($payload['partner_id'] ?? null),
            'partner_code' => $this->toNullableString($payload['partner_code'] ?? null),
            'partner_name' => $this->toNullableString($payload['partner_name'] ?? null),
            'closing_date' => $this->toNullableString($payload['closing_date'] ?? null),
            'billing_amount' => $this->toNullableInt($payload['billing_amount'] ?? null),
            'invoice_number' => $this->toNullableString($payload['invoice_number'] ?? null),
            'print_type' => $this->toNullableString($payload['print_type'] ?? null),
            'document_type' => $this->toNullableString($payload['document_type'] ?? null),
            'file_type' => $this->toNullableString($payload['file_type'] ?? null),
            'metadata' => $metadata,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMetadata(mixed $metadata): array
    {
        if (is_array($metadata)) {
            return $metadata;
        }

        if (is_string($metadata) && $metadata !== '') {
            $decoded = json_decode($metadata, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function toNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = trim((string) $value);

        return $string === '' ? null : $string;
    }

    private function toNullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }
}
