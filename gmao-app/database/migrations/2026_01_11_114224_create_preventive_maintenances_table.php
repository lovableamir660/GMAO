<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Plans de maintenance préventive
        Schema::create('preventive_maintenances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            
            // Fréquence
            $table->enum('frequency_type', ['daily', 'weekly', 'monthly', 'yearly', 'counter'])->default('monthly');
            $table->integer('frequency_value')->default(1); // tous les X jours/semaines/mois...
            $table->integer('counter_threshold')->nullable(); // Pour maintenance basée compteur
            $table->string('counter_unit')->nullable(); // heures, km, cycles...
            
            // Planification
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->date('last_execution_date')->nullable();
            $table->date('next_execution_date')->nullable();
            
            // Paramètres OT généré
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->integer('estimated_duration')->nullable(); // minutes
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            
            // État
            $table->boolean('is_active')->default(true);
            $table->integer('advance_days')->default(7); // Générer OT X jours avant
            
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });

        // Tâches du plan de maintenance
        Schema::create('preventive_maintenance_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preventive_maintenance_id')->constrained()->cascadeOnDelete();
            $table->integer('order')->default(0);
            $table->string('description');
            $table->integer('estimated_duration')->nullable(); // minutes
            $table->text('instructions')->nullable();
            $table->boolean('requires_part')->default(false);
            $table->timestamps();
        });

        // Lien entre plan et pièces nécessaires
        Schema::create('preventive_maintenance_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preventive_maintenance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();
        });

        // Historique des exécutions
        Schema::create('preventive_maintenance_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('preventive_maintenance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->date('scheduled_date');
            $table->date('executed_date')->nullable();
            $table->enum('status', ['scheduled', 'generated', 'completed', 'skipped'])->default('scheduled');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preventive_maintenance_logs');
        Schema::dropIfExists('preventive_maintenance_parts');
        Schema::dropIfExists('preventive_maintenance_tasks');
        Schema::dropIfExists('preventive_maintenances');
    }
};
