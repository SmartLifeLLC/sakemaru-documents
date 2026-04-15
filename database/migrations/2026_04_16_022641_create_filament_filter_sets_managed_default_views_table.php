<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filament_filter_sets_managed_default_views', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->integer('tenant_id')->nullable();
            $table->string('resource');
            $table->string('view_type');
            $table->string('view');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::drop('filament_filter_sets_managed_default_views');
    }
};
