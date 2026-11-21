<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 30);
            $table->string('external_id', 64);
            $table->foreignId('category_id')->constrained();
            $table->string('title', 255);
            $table->text('description');
            $table->decimal('price', 10, 2);
            $table->string('image_url', 2048);
            $table->decimal('rating_rate', 3, 2);
            $table->integer('rating_count');
            $table->timestampTz('imported_at');
            $table->timestamps();

            $table->unique(['source', 'external_id']);
            $table->index(['category_id', 'rating_rate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
