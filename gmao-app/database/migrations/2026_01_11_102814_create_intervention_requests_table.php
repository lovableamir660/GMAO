<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('intervention_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('title');
            $table->text('description');
            $table->enum('urgency', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['pending', 'approved', 'rejected', 'converted'])->default('pending');
            $table->boolean('machine_stopped')->default(false);
            $table->string('location_details')->nullable(); // Précisions sur l'emplacement
            $table->string('contact_phone')->nullable();
            
            // Validation
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->datetime('validated_at')->nullable();
            $table->text('validation_comment')->nullable();
            
            // Conversion en OT
            $table->foreignId('work_order_id')->nullable()->constrained()->nullOnDelete();
            $table->datetime('converted_at')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('intervention_requests');
    }
};
