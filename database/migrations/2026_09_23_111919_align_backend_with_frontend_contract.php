<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'agency_code')) {
                $table->string('agency_code', 30)->nullable()->after('status');
            }
            if (! Schema::hasColumn('users', 'zone_codes')) {
                $table->json('zone_codes')->nullable()->after('agency_code');
            }
            if (! Schema::hasColumn('users', 'available')) {
                $table->boolean('available')->default(true)->after('zone_codes');
            }
        });

        Schema::table('financial_accounts', function (Blueprint $table) {
            if (! Schema::hasColumn('financial_accounts', 'available_balance')) {
                $table->decimal('available_balance', 15, 2)->nullable()->after('balance');
            }
            if (! Schema::hasColumn('financial_accounts', 'blocked_balance')) {
                $table->decimal('blocked_balance', 15, 2)->default(0)->after('available_balance');
            }
            if (! Schema::hasColumn('financial_accounts', 'agency_code')) {
                $table->string('agency_code', 30)->nullable()->after('status');
            }
        });

        Schema::table('account_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('account_transactions', 'type')) {
                $table->string('type', 50)->nullable()->after('transaction_type');
            }
            if (! Schema::hasColumn('account_transactions', 'direction')) {
                $table->string('direction', 10)->nullable()->after('type');
            }
            if (! Schema::hasColumn('account_transactions', 'label')) {
                $table->string('label', 255)->nullable()->after('direction');
            }
            if (! Schema::hasColumn('account_transactions', 'booked_at')) {
                $table->dateTime('booked_at')->nullable()->after('transaction_date');
            }
            if (! Schema::hasColumn('account_transactions', 'status')) {
                $table->string('status', 30)->nullable()->after('booked_at');
            }
            if (! Schema::hasColumn('account_transactions', 'channel')) {
                $table->string('channel', 80)->nullable()->after('status');
            }
            if (! Schema::hasColumn('account_transactions', 'balance_after')) {
                $table->decimal('balance_after', 15, 2)->nullable()->after('channel');
            }
        });

        Schema::table('credit_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('credit_requests', 'agency_code')) {
                $table->string('agency_code', 30)->nullable()->after('status');
            }
            if (! Schema::hasColumn('credit_requests', 'zone_code')) {
                $table->string('zone_code', 80)->nullable()->after('agency_code');
            }
            if (! Schema::hasColumn('credit_requests', 'assigned_agent_id')) {
                $table->foreignId('assigned_agent_id')->nullable()->after('zone_code')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('credit_requests', 'ongoing_credit_count')) {
                $table->unsignedInteger('ongoing_credit_count')->default(0)->after('declared_monthly_expenses');
            }
            if (! Schema::hasColumn('credit_requests', 'complement_subject')) {
                $table->string('complement_subject', 40)->nullable()->after('submitted_at');
            }
            if (! Schema::hasColumn('credit_requests', 'complement_detail')) {
                $table->text('complement_detail')->nullable()->after('complement_subject');
            }
            if (! Schema::hasColumn('credit_requests', 'adjourn_reason')) {
                $table->string('adjourn_reason', 500)->nullable()->after('complement_detail');
            }
            if (! Schema::hasColumn('credit_requests', 'adjourn_what')) {
                $table->string('adjourn_what', 500)->nullable()->after('adjourn_reason');
            }
            if (! Schema::hasColumn('credit_requests', 'assignment_reason')) {
                $table->string('assignment_reason', 500)->nullable()->after('adjourn_what');
            }
        });

        Schema::table('loans', function (Blueprint $table) {
            if (! Schema::hasColumn('loans', 'savings_account_id')) {
                $table->foreignId('savings_account_id')->nullable()->after('credit_request_id')->constrained('financial_accounts')->nullOnDelete();
            }
            if (! Schema::hasColumn('loans', 'funds_received')) {
                $table->decimal('funds_received', 15, 2)->default(0)->after('outstanding_amount');
            }
        });

        Schema::table('credit_committee_decisions', function (Blueprint $table) {
            if (! Schema::hasColumn('credit_committee_decisions', 'reason')) {
                $table->string('reason', 500)->nullable()->after('comment');
            }
            if (! Schema::hasColumn('credit_committee_decisions', 'what')) {
                $table->string('what', 500)->nullable()->after('reason');
            }
            if (! Schema::hasColumn('credit_committee_decisions', 'subject')) {
                $table->string('subject', 40)->nullable()->after('what');
            }
            if (! Schema::hasColumn('credit_committee_decisions', 'complement_detail')) {
                $table->text('complement_detail')->nullable()->after('subject');
            }
        });

        if (! Schema::hasTable('agent_assignment_logs')) {
            Schema::create('agent_assignment_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('credit_request_id')->constrained('credit_requests')->cascadeOnDelete();
                $table->foreignId('assigned_by')->constrained('users')->cascadeOnDelete();
                $table->foreignId('previous_agent_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('new_agent_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reason', 500);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('routing_zones')) {
            Schema::create('routing_zones', function (Blueprint $table) {
                $table->id();
                $table->string('agency_code', 30);
                $table->string('code', 80);
                $table->string('name', 150);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['agency_code', 'code']);
            });
        }

        $this->remapLegacyStatuses();
        $this->seedDefaultZones();
    }

    public function down(): void
    {
        Schema::dropIfExists('routing_zones');
        Schema::dropIfExists('agent_assignment_logs');

        if (Schema::hasColumn('credit_committee_decisions', 'reason')) {
            Schema::table('credit_committee_decisions', function (Blueprint $table) {
                $table->dropColumn(['reason', 'what', 'subject', 'complement_detail']);
            });
        }

        if (Schema::hasColumn('loans', 'savings_account_id')) {
            Schema::table('loans', function (Blueprint $table) {
                $table->dropConstrainedForeignId('savings_account_id');
                $table->dropColumn('funds_received');
            });
        }

        if (Schema::hasColumn('credit_requests', 'assigned_agent_id')) {
            Schema::table('credit_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('assigned_agent_id');
                $table->dropColumn([
                    'agency_code',
                    'zone_code',
                    'ongoing_credit_count',
                    'complement_subject',
                    'complement_detail',
                    'adjourn_reason',
                    'adjourn_what',
                    'assignment_reason',
                ]);
            });
        }

        if (Schema::hasColumn('account_transactions', 'type')) {
            Schema::table('account_transactions', function (Blueprint $table) {
                $table->dropColumn(['type', 'direction', 'label', 'booked_at', 'status', 'channel', 'balance_after']);
            });
        }

        if (Schema::hasColumn('financial_accounts', 'available_balance')) {
            Schema::table('financial_accounts', function (Blueprint $table) {
                $table->dropColumn(['available_balance', 'blocked_balance', 'agency_code']);
            });
        }

        if (Schema::hasColumn('users', 'agency_code')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn(['agency_code', 'zone_codes', 'available']);
            });
        }
    }

    private function remapLegacyStatuses(): void
    {
        $map = [
            'ANALYSIS' => 'IN_ANALYSIS',
            'CREDIT_REVIEW' => 'PENDING_COMMITTEE',
            'DISBURSED' => 'APPROVED',
        ];

        foreach ($map as $from => $to) {
            if (Schema::hasTable('credit_requests')) {
                DB::table('credit_requests')->where('status', $from)->update(['status' => $to]);
            }

            if (Schema::hasTable('credit_status_history')) {
                DB::table('credit_status_history')->where('old_status', $from)->update(['old_status' => $to]);
                DB::table('credit_status_history')->where('new_status', $from)->update(['new_status' => $to]);
            }
        }
    }

    private function seedDefaultZones(): void
    {
        if (! Schema::hasTable('routing_zones')) {
            return;
        }

        $now = now();
        $rows = [
            ['agency_code' => 'BKO', 'code' => 'BKO-CENTRE', 'name' => 'Bamako Centre', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['agency_code' => 'BKO', 'code' => 'BKO-EST', 'name' => 'Bamako Est', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['agency_code' => 'BKO', 'code' => 'BKO-OUEST', 'name' => 'Bamako Ouest', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['agency_code' => 'SGO', 'code' => 'SGO-CENTRE', 'name' => 'Ségou Centre', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['agency_code' => 'SKO', 'code' => 'SKO-CENTRE', 'name' => 'Sikasso Centre', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ];

        foreach ($rows as $row) {
            DB::table('routing_zones')->updateOrInsert(
                ['agency_code' => $row['agency_code'], 'code' => $row['code']],
                $row
            );
        }
    }
};
