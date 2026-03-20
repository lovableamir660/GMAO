<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('cascade'); // null = tous les users du site
            $table->string('type'); // stock_critical, wo_overdue, wo_assigned, pm_upcoming, equipment_down
            $table->string('title');
            $table->text('message');
            $table->string('icon')->default('🔔');
            $table->string('color')->default('info'); // info, warning, danger, success
            $table->string('link')->nullable(); // URL vers la ressource concernée
            $table->string('reference_type')->nullable(); // App\Models\Part, App\Models\WorkOrder, etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['site_id', 'user_id', 'read_at']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
