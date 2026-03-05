<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funding_products', function (Blueprint $table) {
            if (! Schema::hasColumn('funding_products', 'min_amount')) {
                $table->decimal('min_amount', 20, 2)->default(10)->after('profit_percent');
            }
        });

        Schema::table('trader_cycles', function (Blueprint $table) {
            if (! Schema::hasColumn('trader_cycles', 'packages_quantity')) {
                $table->unsignedInteger('packages_quantity')->default(1)->after('amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trader_cycles', function (Blueprint $table) {
            if (Schema::hasColumn('trader_cycles', 'packages_quantity')) {
                $table->dropColumn('packages_quantity');
            }
        });

        Schema::table('funding_products', function (Blueprint $table) {
            if (Schema::hasColumn('funding_products', 'min_amount')) {
                $table->dropColumn('min_amount');
            }
        });
    }
};
