<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('group', 50)->index();       // ex: general, equipment, work_order...
            $table->string('key', 100);                  // ex: types, priorities, company_name...
            $table->text('value')->nullable();            // JSON pour les listes, texte pour les scalaires
            $table->string('type', 20)->default('string'); // string, integer, boolean, json, list
            $table->string('label');                      // Libellé affiché
            $table->string('description')->nullable();    // Aide contextuelle
            $table->boolean('is_system')->default(false); // Non supprimable si true
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
