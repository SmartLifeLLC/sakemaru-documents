<?php

namespace App\Services;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class InvoiceFileUploadService
{
    /**
     * Upload an invoice file to S3.
     *
     * @param UploadedFile $file
     * @param string $category 'issued' or 'received'
     * @param string $transactionDate YYYY-MM-DD
     * @param User|null $partner
     * @param float $amount
     * @return array{path: string, hash: string}
     */
    public function upload(UploadedFile $file, string $category, string $transactionDate, ?User $partner, float $amount): array
    {
        // 1. Get Client ID (Single Tenant, so just get the first one or create if missing)
        $client = Client::first();
        if (!$client) {
            // Fallback or error. For now, assuming seed or manual creation.
            // Let's use ID 1 if no client exists, but ideally Client should exist.
             $clientId = 1;
        } else {
            $clientId = $client->id;
        }

        // 2. Determine Owner Dir
        // If partner is null, it's self-managed (0). If partner exists, use partner ID.
        $ownerDir = $partner ? $partner->id : 0;

        // 3. Parse Date for Year/Month
        $date = \Carbon\Carbon::parse($transactionDate);
        $year = $date->year;
        $month = $date->format('m');
        $ymd = $date->format('Ymd');

        // 4. Generate Filename
        // {category}_{ymd}_{partner}_{amount}_{uuid}.pdf
        // Partner part: If partner exists, use name (sanitized), else 'self' or similar?
        // Spec says: {partner}
        $partnerName = $partner ? Str::slug($partner->name) : 'self';
        $uuid = Str::uuid();
        $extension = $file->getClientOriginalExtension();
        
        $filename = "{$category}_{$ymd}_{$partnerName}_{$amount}_{$uuid}.{$extension}";

        // 5. Construct S3 Path
        // documents/{client_id}/{owner_dir}/{year}/{month}/{filename}
        $path = "documents/{$clientId}/{$ownerDir}/{$year}/{$month}/{$filename}";

        // 6. Calculate Hash
        $hash = hash_file('sha256', $file->getRealPath());

        // 7. Upload to S3
        Storage::disk('s3')->put($path, file_get_contents($file->getRealPath()));

        return [
            'path' => $path,
            'hash' => $hash,
        ];
    }
}
