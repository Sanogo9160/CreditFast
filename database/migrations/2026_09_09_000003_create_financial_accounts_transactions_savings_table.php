<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->string('account_number', 50)->unique();
            $table->string('account_type', 30)->default('SAVINGS');
            $table->decimal('balance', 15, 2)->default(0);
            $table->date('opened_at')->nullable();
            $table->string('status', 30)->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('account_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained('financial_accounts')->cascadeOnDelete();
            $table->string('transaction_type', 30);
            $table->decimal('amount', 15, 2);
            $table->dateTime('transaction_date');
            $table->string('reference', 100)->nullable();
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('savings_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('account_id')->nullable()->constrained('financial_accounts')->nullOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('total_deposits', 15, 2)->default(0);
            $table->decimal('total_withdrawals', 15, 2)->default(0);
            $table->integer('deposit_count')->default(0);
            $table->integer('withdrawal_count')->default(0);
            $table->decimal('average_balance', 15, 2)->default(0);
            $table->decimal('closing_balance', 15, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('savings_history');
        Schema::dropIfExists('account_transactions');
        Schema::dropIfExists('financial_accounts');
    }
};
