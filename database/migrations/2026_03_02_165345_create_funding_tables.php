<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('funding_products', function (Blueprint $table) {
            $table->id();
	    $table->string('name');
	    $table->integer('freeze_days');
	    $table->decimal('profit_percent', 8, 2);
            $table->decimal('max_total_volume', 20, 2)->default(0);
            $table->decimal('current_volume', 20, 2)->default(0);
	    $table->decimal('max_per_trader', 20, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('trader_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_id')->constrained('funding_products');
            $table->decimal('amount', 20, 5);
            $table->decimal('profit_percent', 8, 2);
            $table->timestamp('funded_at');
            $table->timestamp('return_at');
            $table->string('status')->default('active');
            $table->foreignId('confirmed_by_admin_id')->nullable()->constrained('users');
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('wallets') && !Schema::hasColumn('wallets', 'locked_balance')) {
            Schema::table('wallets', function (Blueprint $table) {
               $table->decimal('locked_balance', 20, 2)->default(0)->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('funding_tables');
        Schema::dropIfExists('funding_products');

        if (Schema::hasColumn('wallets', 'locked_balance')) {
            Schema::table('wallets', function (Blueprint $table) {
               $table->dropColumn('locked_balance');
            });
       }
    }
};
