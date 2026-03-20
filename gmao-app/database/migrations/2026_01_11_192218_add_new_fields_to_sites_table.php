<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            if (!Schema::hasColumn('sites', 'postal_code')) {
                $table->string('postal_code', 20)->nullable();
            }
            
            if (!Schema::hasColumn('sites', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable();
            }
            
            if (!Schema::hasColumn('sites', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable();
            }
            
            if (!Schema::hasColumn('sites', 'contact_name')) {
                $table->string('contact_name')->nullable();
            }
            
            if (!Schema::hasColumn('sites', 'contact_phone')) {
                $table->string('contact_phone', 50)->nullable();
            }
            
            if (!Schema::hasColumn('sites', 'contact_email')) {
                $table->string('contact_email')->nullable();
            }
            
            if (!Schema::hasColumn('sites', 'site_type')) {
                $table->string('site_type', 20)->default('other');
            }
            
            if (!Schema::hasColumn('sites', 'capacity')) {
                $table->string('capacity', 100)->nullable();
            }
            
            if (!Schema::hasColumn('sites', 'operating_hours')) {
                $table->string('operating_hours', 100)->nullable();
            }
            
            if (!Schema::hasColumn('sites', 'notes')) {
                $table->text('notes')->nullable();
            }
            
            if (!Schema::hasColumn('sites', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Ajouter les index séparément avec try/catch
        try {
            Schema::table('sites', function (Blueprint $table) {
                $table->index('city', 'sites_city_index');
            });
        } catch (\Exception $e) {
            // Index existe déjà
        }

        try {
            Schema::table('sites', function (Blueprint $table) {
                $table->index('site_type', 'sites_site_type_index');
            });
        } catch (\Exception $e) {
            // Index existe déjà
        }

        try {
            Schema::table('sites', function (Blueprint $table) {
                $table->index('is_active', 'sites_is_active_index');
            });
        } catch (\Exception $e) {
            // Index existe déjà
        }
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $columns = [
                'postal_code',
                'latitude',
                'longitude',
                'contact_name',
                'contact_phone',
                'contact_email',
                'site_type',
                'capacity',
                'operating_hours',
                'notes',
                'deleted_at',
            ];
            
            $existingColumns = [];
            foreach ($columns as $column) {
                if (Schema::hasColumn('sites', $column)) {
                    $existingColumns[] = $column;
                }
            }
            
            if (!empty($existingColumns)) {
                $table->dropColumn($existingColumns);
            }
        });

        // Supprimer les index
        try {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropIndex('sites_city_index');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropIndex('sites_site_type_index');
            });
        } catch (\Exception $e) {}

        try {
            Schema::table('sites', function (Blueprint $table) {
                $table->dropIndex('sites_is_active_index');
            });
        } catch (\Exception $e) {}
    }
};
