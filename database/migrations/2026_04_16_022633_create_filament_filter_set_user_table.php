<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filament_filter_set_user', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->foreignId('filter_set_id')->references('id')->on('filament_filter_sets')->constrained()->cascadeOnDelete();
            $table->smallInteger('sort_order')->default(1);
        });
    }

    public function down(): void
    {
        Schema::drop('filament_filter_set_user');
    }
};
