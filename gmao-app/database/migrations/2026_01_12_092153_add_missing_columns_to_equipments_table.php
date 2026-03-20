<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            // Ajouter les colonnes manquantes si elles n'existent pas
            if (!Schema::hasColumn('equipments', 'specifications')) {
                $table->json('specifications')->nullable();
            }
            if (!Schema::hasColumn('equipments', 'category')) {
                $table->string('category')->nullable();
            }
            if (!Schema::hasColumn('equipments', 'year')) {
                $table->year('year')->nullable();
            }
            if (!Schema::hasColumn('equipments', 'location')) {
                $table->string('location')->nullable();
            }
            if (!Schema::hasColumn('equipments', 'acquisition_date')) {
                $table->date('acquisition_date')->nullable();
            }
            if (!Schema::hasColumn('equipments', 'acquisition_cost')) {
                $table->decimal('acquisition_cost', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('equipments', 'warranty_expiry')) {
                $table->date('warranty_expiry')->nullable();
            }
            if (!Schema::hasColumn('equipments', 'last_maintenance_date')) {
                $table->date('last_maintenance_date')->nullable();
            }
            if (!Schema::hasColumn('equipments', 'next_maintenance_date')) {
                $table->date('next_maintenance_date')->nullable();
            }
            if (!Schema::hasColumn('equipments', 'hour_counter')) {
                $table->integer('hour_counter')->default(0);
            }
            if (!Schema::hasColumn('equipments', 'photo')) {
                $table->string('photo')->nullable();
            }
            if (!Schema::hasColumn('equipments', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('equipments', function (Blueprint $table) {
            $columns = [
                'specifications', 'category', 'year', 'location',
                'acquisition_date', 'acquisition_cost', 'warranty_expiry',
                'last_maintenance_date', 'next_maintenance_date',
                'hour_counter', 'photo', 'is_active'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('equipments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
