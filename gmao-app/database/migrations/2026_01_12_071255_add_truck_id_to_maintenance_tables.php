<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Table des demandes d'intervention (DI)
        if (Schema::hasTable('intervention_requests')) {
            Schema::table('intervention_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('intervention_requests', 'truck_id')) {
                    $table->foreignId('truck_id')->nullable()->after('equipment_id')->constrained()->nullOnDelete();
                }
                if (!Schema::hasColumn('intervention_requests', 'asset_type')) {
                    $table->enum('asset_type', ['equipment', 'truck'])->default('equipment')->after('id');
                }
            });
        }

        // Table des interventions
        if (Schema::hasTable('interventions')) {
            Schema::table('interventions', function (Blueprint $table) {
                if (!Schema::hasColumn('interventions', 'truck_id')) {
                    $table->foreignId('truck_id')->nullable()->after('equipment_id')->constrained()->nullOnDelete();
                }
                if (!Schema::hasColumn('interventions', 'asset_type')) {
                    $table->enum('asset_type', ['equipment', 'truck'])->default('equipment')->after('id');
                }
            });
        }

        // Table maintenance préventive
        if (Schema::hasTable('preventive_maintenances')) {
            Schema::table('preventive_maintenances', function (Blueprint $table) {
                if (!Schema::hasColumn('preventive_maintenances', 'truck_id')) {
                    $table->foreignId('truck_id')->nullable()->after('equipment_id')->constrained()->nullOnDelete();
                }
                if (!Schema::hasColumn('preventive_maintenances', 'asset_type')) {
                    $table->enum('asset_type', ['equipment', 'truck'])->default('equipment')->after('id');
                }
            });
        }

        // Table des plannings de maintenance préventive
        if (Schema::hasTable('preventive_schedules')) {
            Schema::table('preventive_schedules', function (Blueprint $table) {
                if (!Schema::hasColumn('preventive_schedules', 'truck_id')) {
                    $table->foreignId('truck_id')->nullable()->after('equipment_id')->constrained()->nullOnDelete();
                }
                if (!Schema::hasColumn('preventive_schedules', 'asset_type')) {
                    $table->enum('asset_type', ['equipment', 'truck'])->default('equipment')->after('id');
                }
                // Pour les camions : maintenance basée sur le kilométrage
                if (!Schema::hasColumn('preventive_schedules', 'mileage_interval')) {
                    $table->integer('mileage_interval')->nullable()->after('frequency_days');
                }
                if (!Schema::hasColumn('preventive_schedules', 'last_mileage')) {
                    $table->integer('last_mileage')->nullable()->after('mileage_interval');
                }
                if (!Schema::hasColumn('preventive_schedules', 'next_mileage')) {
                    $table->integer('next_mileage')->nullable()->after('last_mileage');
                }
            });
        }
    }

    public function down(): void
    {
        $tables = ['intervention_requests', 'interventions', 'preventive_maintenances', 'preventive_schedules'];
        
        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (Schema::hasColumn($tableName, 'truck_id')) {
                        $table->dropForeign([$tableName . '_truck_id_foreign']);
                        $table->dropColumn('truck_id');
                    }
                    if (Schema::hasColumn($tableName, 'asset_type')) {
                        $table->dropColumn('asset_type');
                    }
                });
            }
        }

        if (Schema::hasTable('preventive_schedules')) {
            Schema::table('preventive_schedules', function (Blueprint $table) {
                $columns = ['mileage_interval', 'last_mileage', 'next_mileage'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('preventive_schedules', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
