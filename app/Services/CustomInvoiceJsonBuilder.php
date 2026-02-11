<?php

namespace App\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class CustomInvoiceJsonBuilder
{
    /**
     * @param  array<string, mixed>  $formData
     * @return array<string, mixed>
     */
    public function build(string $commandType, array $formData, int|string|null $requestedByUserId = null): array
    {
        $commandType = trim($commandType);
        if (! in_array($commandType, ['create', 'revise'], true)) {
            throw new InvalidArgumentException('commandType must be create or revise');
        }

        $documentType = trim((string) ($formData['document_type'] ?? ''));
        if (! in_array($documentType, ['invoice', 'delivery_note', 'rebate_invoice'], true)) {
            throw new InvalidArgumentException('document_type must be invoice, delivery_note, or rebate_invoice');
        }

        $issueDate = trim((string) ($formData['issue_date'] ?? ''));
        if ($issueDate === '') {
            throw new InvalidArgumentException('issue_date is required');
        }

        $sourceDocumentUuid = $this->toNullableString($formData['source_uuid'] ?? null);
        if ($commandType === 'revise' && $sourceDocumentUuid === null) {
            throw new InvalidArgumentException('source_uuid is required when command_type is revise');
        }

        $requestUuid = $this->resolveRequestUuid($formData['request_uuid'] ?? null);
        $requestedBy = $this->resolveRequestedByUserId($requestedByUserId);
        $lines = $this->buildLines($formData['lines'] ?? []);

        if ($lines === []) {
            throw new InvalidArgumentException('at least one line is required');
        }

        return [
            'schema_version' => '1.0',
            'command_type' => $commandType,
            'request_context' => [
                'request_uuid' => $requestUuid,
                'requested_at' => now()->toIso8601String(),
                'requested_by_system' => 'documents',
                'requested_by_user_id' => 'company_user:'.$requestedBy,
            ],
            'target' => [
                'client_id' => $this->toNullableInt($formData['client_id'] ?? null),
                'partner_id' => $this->toNullableInt($formData['partner_id'] ?? null),
                'document_type' => $documentType,
                'source_document_uuid' => $sourceDocumentUuid,
            ],
            'document' => [
                'document_number' => $this->toNullableString($formData['document_number'] ?? null),
                'issue_date' => $issueDate,
                'closing_date' => $this->toNullableString($formData['closing_date'] ?? null),
                'due_date' => $this->toNullableString($formData['due_date'] ?? null),
                'currency' => $this->toNullableString($formData['currency'] ?? null) ?? 'JPY',
                'title' => $this->toNullableString($formData['title'] ?? null) ?? '',
                'remarks' => (string) ($formData['remarks'] ?? ''),
            ],
            'counterparty' => [
                'name' => $this->toNullableString($formData['partner_name'] ?? null),
                'code' => $this->toNullableString($formData['partner_code'] ?? null),
                'billing_email' => $this->toNullableString($formData['billing_email'] ?? null),
            ],
            'lines' => $lines,
            'totals' => $this->buildTotals($lines),
            'attachments' => [],
            'publish_control' => [
                'requires_manual_publish' => true,
            ],
        ];
    }

    /**
     * @param  mixed  $rawLines
     * @return array<int, array<string, mixed>>
     */
    private function buildLines(mixed $rawLines): array
    {
        if (! is_array($rawLines)) {
            return [];
        }

        $lines = [];
        foreach (array_values($rawLines) as $index => $rawLine) {
            if (! is_array($rawLine)) {
                continue;
            }

            $itemName = trim((string) ($rawLine['item_name'] ?? ''));
            $quantity = $this->toNumericString($rawLine['quantity'] ?? null);
            $unitPrice = $this->toNullableInt($rawLine['unit_price'] ?? null) ?? 0;
            $amount = $this->toNullableInt($rawLine['amount'] ?? null) ?? (int) round((float) $quantity * $unitPrice);
            $taxRate = $this->toNullableInt($rawLine['tax_rate'] ?? null) ?? 10;
            $taxAmount = $this->toNullableInt($rawLine['tax_amount'] ?? null) ?? (int) round($amount * ($taxRate / 100));

            if ($itemName === '' && $amount === 0 && $taxAmount === 0) {
                continue;
            }

            $lines[] = [
                'line_no' => $index + 1,
                'item_code' => $this->toNullableString($rawLine['item_code'] ?? null),
                'item_name' => $itemName,
                'quantity' => $quantity,
                'unit' => $this->toNullableString($rawLine['unit'] ?? null),
                'unit_price' => $unitPrice,
                'amount' => $amount,
                'tax_rate' => (string) $taxRate,
                'tax_amount' => $taxAmount,
                'note' => (string) ($rawLine['note'] ?? ''),
            ];
        }

        return $lines;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<string, int>
     */
    private function buildTotals(array $lines): array
    {
        $subtotal = 0;
        $taxTotal = 0;

        foreach ($lines as $line) {
            $subtotal += (int) ($line['amount'] ?? 0);
            $taxTotal += (int) ($line['tax_amount'] ?? 0);
        }

        return [
            'subtotal' => $subtotal,
            'tax_total' => $taxTotal,
            'grand_total' => $subtotal + $taxTotal,
        ];
    }

    private function resolveRequestUuid(mixed $requestUuid): string
    {
        $requestUuid = trim((string) $requestUuid);

        return Str::isUuid($requestUuid) ? $requestUuid : (string) Str::uuid();
    }

    private function resolveRequestedByUserId(int|string|null $requestedByUserId): int
    {
        if ($requestedByUserId === null || $requestedByUserId === '') {
            $requestedByUserId = auth('web')->id() ?? auth()->id();
        }

        $requestedBy = $this->toNullableInt($requestedByUserId);
        if ($requestedBy === null || $requestedBy <= 0) {
            throw new RuntimeException('requested_by_user_id could not be resolved.');
        }

        return $requestedBy;
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

    private function toNumericString(mixed $value): string
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return '0';
        }

        return (string) $value;
    }
}
