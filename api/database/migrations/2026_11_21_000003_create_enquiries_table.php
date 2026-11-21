<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->string('product_title', 255);
            $table->string('name', 100);
            $table->string('email', 255);
            $table->string('phone', 30)->nullable();
            $table->text('message')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestampTz('mailed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
