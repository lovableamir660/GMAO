<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driver_habilitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('driver_id')->constrained()->onDelete('cascade');
            $table->foreignId('habilitation_id')->constrained()->onDelete('cascade');
            $table->date('obtained_date');
            $table->date('expiry_date')->nullable();
            $table->string('certificate_number')->nullable();
            $table->string('status')->default('valid'); // valid, expired, suspended, pending
            $table->string('document_path')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->unique(['driver_id', 'habilitation_id']);
            $table->index(['status', 'expiry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driver_habilitations');
    }
};
