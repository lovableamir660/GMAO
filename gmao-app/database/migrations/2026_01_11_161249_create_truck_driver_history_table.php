<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('truck_driver_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->foreignId('truck_id')->constrained()->onDelete('cascade');
            $table->foreignId('driver_id')->constrained()->onDelete('cascade');
            $table->datetime('assigned_at');
            $table->datetime('unassigned_at')->nullable();
            $table->integer('start_mileage')->nullable();
            $table->integer('end_mileage')->nullable();
            $table->string('assignment_reason')->nullable(); // mission, remplacement, formation, etc.
            $table->string('unassignment_reason')->nullable(); // fin_mission, panne, congé, etc.
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users');
            $table->foreignId('unassigned_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['truck_id', 'assigned_at']);
            $table->index(['driver_id', 'assigned_at']);
            $table->index(['site_id', 'assigned_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('truck_driver_history');
    }
};
