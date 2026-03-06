<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trader_cycles', function (Blueprint $table) {
            if (! Schema::hasColumn('trader_cycles', 'product_name')) {
                $table->string('product_name')->nullable()->after('product_id');
            }

            if (! Schema::hasColumn('trader_cycles', 'profit_amount')) {
                $table->decimal('profit_amount', 20, 2)->nullable()->after('profit_percent');
            }

            if (! Schema::hasColumn('trader_cycles', 'payout_amount')) {
                $table->decimal('payout_amount', 20, 2)->nullable()->after('profit_amount');
            }

            if (! Schema::hasColumn('trader_cycles', 'cancelled_at')) {
                $table->timestamp('cancelled_at')->nullable()->after('cancellation_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trader_cycles', function (Blueprint $table) {
            if (Schema::hasColumn('trader_cycles', 'cancelled_at')) {
                $table->dropColumn('cancelled_at');
            }

            if (Schema::hasColumn('trader_cycles', 'payout_amount')) {
                $table->dropColumn('payout_amount');
            }

            if (Schema::hasColumn('trader_cycles', 'profit_amount')) {
                $table->dropColumn('profit_amount');
            }

            if (Schema::hasColumn('trader_cycles', 'product_name')) {
                $table->dropColumn('product_name');
            }
        });
    }
};
