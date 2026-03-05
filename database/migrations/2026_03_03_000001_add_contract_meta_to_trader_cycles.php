<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trader_cycles', function (Blueprint $table) {
            if (! Schema::hasColumn('trader_cycles', 'cancelled_by_admin_id')) {
                $table->foreignId('cancelled_by_admin_id')->nullable()->after('confirmed_by_admin_id')->constrained('users');
            }

            if (! Schema::hasColumn('trader_cycles', 'cancellation_reason')) {
                $table->text('cancellation_reason')->nullable()->after('cancelled_by_admin_id');
            }

            if (! Schema::hasColumn('trader_cycles', 'is_overdue')) {
                $table->boolean('is_overdue')->default(false)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('trader_cycles', function (Blueprint $table) {
            if (Schema::hasColumn('trader_cycles', 'cancelled_by_admin_id')) {
                $table->dropConstrainedForeignId('cancelled_by_admin_id');
            }

            if (Schema::hasColumn('trader_cycles', 'cancellation_reason')) {
                $table->dropColumn('cancellation_reason');
            }

            if (Schema::hasColumn('trader_cycles', 'is_overdue')) {
                $table->dropColumn('is_overdue');
            }
        });
    }
};
