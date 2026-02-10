<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'sakemaru';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('mysql')->create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id'); // From default connection
            $table->unsignedBigInteger('partner_id')->nullable(); // From default connection (users table), so no FK constraint
            $table->enum('category', ['issued', 'received']);
            $table->enum('source_type', ['system', 'manual']);
            $table->date('transaction_date');
            $table->string('partner_name');
            $table->decimal('amount', 15, 2); // Assuming currency
            $table->string('s3_path');
            $table->string('file_hash');
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('mysql')->dropIfExists('documents');
    }
};
