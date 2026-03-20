<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            if (!Schema::hasColumn('equipments', 'location_id')) {
                $table->unsignedBigInteger('location_id')->nullable()->after('status');
            }
            if (!Schema::hasColumn('equipments', 'department')) {
                $table->string('department')->nullable()->after('location_id');
            }
            if (!Schema::hasColumn('equipments', 'criticality')) {
                $table->enum('criticality', ['low', 'medium', 'high', 'critical'])->default('medium')->after('department');
            }
            if (!Schema::hasColumn('equipments', 'installation_date')) {
                $table->date('installation_date')->nullable()->after('criticality');
            }
            if (!Schema::hasColumn('equipments', 'warranty_expiry_date')) {
                $table->date('warranty_expiry_date')->nullable()->after('installation_date');
            }
            if (!Schema::hasColumn('equipments', 'description')) {
                $table->text('description')->nullable()->after('warranty_expiry_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $table->dropColumn(['location_id', 'department', 'criticality', 'installation_date', 'warranty_expiry_date', 'description']);
        });
    }
};
