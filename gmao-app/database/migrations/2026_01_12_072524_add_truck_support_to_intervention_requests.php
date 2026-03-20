<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

    public function down(): void
    {
        Schema::table('intervention_requests', function (Blueprint $table) {
            $table->dropForeign(['truck_id']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn(['asset_type', 'truck_id', 'rejected_by', 'rejected_at', 'rejection_reason']);
        });
    }
};
