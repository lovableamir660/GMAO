<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('category')->nullable();
            $table->string('unit')->default('unité');
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->integer('quantity_in_stock')->default(0);
            $table->integer('minimum_stock')->default(0);
            $table->integer('maximum_stock')->nullable();
            $table->string('location_in_warehouse')->nullable();
            $table->string('barcode')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('manufacturer_reference')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Table pivot pour lier les pièces aux équipements (BOM)
        Schema::create('equipment_part', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipment_id')->constrained('equipments')->cascadeOnDelete();
            $table->foreignId('part_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->timestamps();

            $table->unique(['equipment_id', 'part_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipment_part');
        Schema::dropIfExists('parts');
    }
};
