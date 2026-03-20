<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->string('code')->unique();
            $table->string('registration_number'); // Immatriculation
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->integer('year')->nullable();
            $table->string('type')->nullable(); // porteur, semi-remorque, citerne, benne, etc.
            $table->decimal('capacity', 10, 2)->nullable(); // Capacité en tonnes ou m³
            $table->string('capacity_unit')->default('tonnes');
            $table->string('fuel_type')->nullable(); // diesel, essence, electrique, etc.
            $table->integer('mileage')->default(0); // Kilométrage
            $table->string('status')->default('available'); // available, in_use, maintenance, out_of_service
            $table->date('registration_date')->nullable();
            $table->date('insurance_expiry_date')->nullable();
            $table->date('technical_inspection_date')->nullable();
            $table->date('next_maintenance_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('photo')->nullable();
            $table->foreignId('current_driver_id')->nullable()->constrained('drivers');
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['site_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucks');
    }
};
