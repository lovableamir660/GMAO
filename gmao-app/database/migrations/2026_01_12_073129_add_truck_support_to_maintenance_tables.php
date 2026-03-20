<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ===== TABLE INTERVENTION_REQUESTS (DI) =====
        if (Schema::hasTable('intervention_requests')) {
            Schema::table('intervention_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('intervention_requests', 'asset_type')) {
                    $table->string('asset_type', 20)->default('equipment')->after('id');
                }
                if (!Schema::hasColumn('intervention_requests', 'truck_id')) {
                    $table->foreignId('truck_id')->nullable()->after('equipment_id')->constrained()->nullOnDelete();
                }
                if (!Schema::hasColumn('intervention_requests', 'rejected_by')) {
                    $table->foreignId('rejected_by')->nullable()->after('validation_comment')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('intervention_requests', 'rejected_at')) {
                    $table->timestamp('rejected_at')->nullable()->after('rejected_by');
                }
                if (!Schema::hasColumn('intervention_requests', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('rejected_at');
                }
            });
        }

        // ===== TABLE WORK_ORDERS (OT) =====
        if (Schema::hasTable('work_orders')) {
            Schema::table('work_orders', function (Blueprint $table) {
                if (!Schema::hasColumn('work_orders', 'asset_type')) {
                    $table->string('asset_type', 20)->default('equipment')->after('id');
                }
                if (!Schema::hasColumn('work_orders', 'truck_id')) {
                    $table->foreignId('truck_id')->nullable()->after('equipment_id')->constrained()->nullOnDelete();
                }
                if (!Schema::hasColumn('work_orders', 'intervention_request_id')) {
                    $table->foreignId('intervention_request_id')->nullable()->after('truck_id')->constrained()->nullOnDelete();
                }
                if (!Schema::hasColumn('work_orders', 'diagnosis')) {
                    $table->text('diagnosis')->nullable()->after('technician_notes');
                }
                if (!Schema::hasColumn('work_orders', 'mileage_at_intervention')) {
                    $table->integer('mileage_at_intervention')->nullable()->after('diagnosis');
                }
                if (!Schema::hasColumn('work_orders', 'cancelled_by')) {
                    $table->foreignId('cancelled_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('work_orders', 'cancelled_at')) {
                    $table->timestamp('cancelled_at')->nullable()->after('cancelled_by');
                }
                if (!Schema::hasColumn('work_orders', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable()->after('cancelled_at');
                }
            });
        }
    }

    public function down(): void
    {
        // Rollback intervention_requests
        if (Schema::hasTable('intervention_requests')) {
            Schema::table('intervention_requests', function (Blueprint $table) {
                if (Schema::hasColumn('intervention_requests', 'truck_id')) {
                    $table->dropForeign(['truck_id']);
                }
                if (Schema::hasColumn('intervention_requests', 'rejected_by')) {
                    $table->dropForeign(['rejected_by']);
                }
            });

            Schema::table('intervention_requests', function (Blueprint $table) {
                $columns = ['asset_type', 'truck_id', 'rejected_by', 'rejected_at', 'rejection_reason'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('intervention_requests', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // Rollback work_orders
        if (Schema::hasTable('work_orders')) {
            Schema::table('work_orders', function (Blueprint $table) {
                if (Schema::hasColumn('work_orders', 'truck_id')) {
                    $table->dropForeign(['truck_id']);
                }
                if (Schema::hasColumn('work_orders', 'intervention_request_id')) {
                    $table->dropForeign(['intervention_request_id']);
                }
                if (Schema::hasColumn('work_orders', 'cancelled_by')) {
                    $table->dropForeign(['cancelled_by']);
                }
            });

            Schema::table('work_orders', function (Blueprint $table) {
                $columns = ['asset_type', 'truck_id', 'intervention_request_id', 'diagnosis', 
                            'mileage_at_intervention', 'cancelled_by', 'cancelled_at', 'cancellation_reason'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('work_orders', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
