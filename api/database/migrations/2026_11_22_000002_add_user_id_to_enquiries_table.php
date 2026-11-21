<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Enquiries from a logged-in customer link to their account; guests leave it null. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('enquiries', function (Blueprint $table): void {
            // Postgres doesn't index foreign keys automatically, so add one for "enquiries of user X".
            $table->foreignId('user_id')->nullable()->after('product_id')->constrained()->nullOnDelete();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('enquiries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
