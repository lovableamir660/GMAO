<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('role_in_site')->nullable(); // rôle spécifique au site (optionnel)
            $table->timestamps();

            $table->unique(['user_id', 'site_id']); // un user ne peut être lié qu'une fois à un site
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_user');
    }
};
