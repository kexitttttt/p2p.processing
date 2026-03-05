<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('funding_products', 'max_per_trader')) {
            Schema::table('funding_products', function (Blueprint $table) {
                $table->integer('max_per_trader')->default(10);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('funding_products', 'max_per_trader')) {
            Schema::table('funding_products', function (Blueprint $table) {
                $table->dropColumn('max_per_trader');
            });
        }
    }
};
