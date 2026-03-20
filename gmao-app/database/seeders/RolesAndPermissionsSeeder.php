<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        // Définir toutes les permissions par module
        $permissions = [
            // Sites
            'site:view_any',
            'site:view',
            'site:create',
            'site:edit',
            'site:delete',

            // Users
            'user:view_any',
            'user:view',
            'user:create',
            'user:update',
            'user:delete',
            'user:assign_roles',

            // Roles
            'role:view_any',
            'role:view',
            'role:create',
            'role:update',
            'role:delete',
            'role:attach_permissions',

            // Equipments
            'equipment:view_any',
            'equipment:view',
            'equipment:view_own',
            'equipment:create',
            'equipment:update',
            'equipment:delete',

            // Locations
            'location:view_any',
            'location:view',
            'location:view_own',
            'location:create',
            'location:update',
            'location:delete',

            // Work Order Requests (Demandes d'intervention)
            'workorder_request:view_any',
            'workorder_request:view',
            'workorder_request:view_own',
            'workorder_request:create',
            'workorder_request:update',
            'workorder_request:delete',
            'workorder_request:approve',

            // Work Orders (Ordres de travail)
            'workorder:view_any',
            'workorder:view',
            'workorder:view_own',
            'workorder:create',
            'workorder:update',
            'workorder:delete',
            'workorder:assign',
            'workorder:start',
            'workorder:log_time',
            'workorder:use_parts',
            'workorder:add_attachments',
            'workorder:comment',
            'workorder:close',
            'workorder:reopen',
            'workorder:approve_close',

            // Parts (Pièces détachées)
            'part:view_any',
            'part:view',
            'part:view_own',
            'part:create',
            'part:update',
            'part:delete',
            'part:link_to_equipment',
            'part:unlink_from_equipment',
            'part:link_to_workorder',
            'part:manage_minmax',
            'part:manage_suppliers',

            // Stock
            'stock:view_any',
            'stock:view',
            'stock:receive',
            'stock:issue',
            'stock:transfer',
            'stock:adjust',
            'stock:inventory',
            'stock:view_valuation',

            // Intervention Requests (Demandes d'intervention)
            'intervention_request:view_any',
            'intervention_request:view',
            'intervention_request:view_own',
            'intervention_request:create',
            'intervention_request:update',
            'intervention_request:delete',
            'intervention_request:validate',
            'intervention_request:convert',

            // Preventive Maintenance
            'preventive:view_any',
            'preventive:view',
            'preventive:view_own',
            'preventive:create',
            'preventive:update',
            'preventive:delete',
            'preventive:generate_wo',

            // Drivers (Chauffeurs)
            'driver:view_any',
            'driver:view',
            'driver:view_own',
            'driver:create',
            'driver:update',
            'driver:delete',

            // Trucks (Camions)
            'truck:view_any',
            'truck:view',
            'truck:view_own',
            'truck:create',
            'truck:update',
            'truck:delete',

            // Assignments (Attributions)
            'assignment:view_any',
            'assignment:view',
            'assignment:view_own',
            'assignment:create',
            'assignment:update',
            'assignment:delete',

            // Clients
            'client:view_any',
            'client:view',
            'client:view_own',
            'client:create',
            'client:update',
            'client:delete',

            // Habilitations
            'habilitation:view_any',
            'habilitation:view',
            'habilitation:view_own',
            'habilitation:create',
            'habilitation:update',
            'habilitation:delete',

            // Reports
            'report:view_any',
            'report:view',
            'report:view_own',
            'report:view_kpi',
            'report:export',

            // ✅ Settings (Paramètres) — nouveau pattern complet
            'setting:view_any',
            'setting:view',
            'setting:create',
            'setting:update',
            'setting:delete',
            'setting:reset',
        ];

        // Créer toutes les permissions
        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }

        // ===== RÔLES =====

        // 1. SuperAdmin - Toutes les permissions
        $superAdmin = Role::firstOrCreate(['name' => 'SuperAdmin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // 2. AdminSite - Gestion complète d'un site
        $adminSite = Role::firstOrCreate(['name' => 'AdminSite', 'guard_name' => 'web']);
        $adminSite->syncPermissions([
            // Sites (limité)
            'site:view_any', 'site:view', 'site:edit',
            // Users
            'user:view_any', 'user:view', 'user:create', 'user:update', 'user:delete', 'user:assign_roles',
            // Roles (lecture)
            'role:view_any', 'role:view',
            // Equipments
            'equipment:view_any', 'equipment:view', 'equipment:create', 'equipment:update', 'equipment:delete',
            // Locations
            'location:view_any', 'location:view', 'location:create', 'location:update', 'location:delete',
            // Work Order Requests
            'workorder_request:view_any', 'workorder_request:view', 'workorder_request:create',
            'workorder_request:update', 'workorder_request:delete', 'workorder_request:approve',
            // Work Orders
            'workorder:view_any', 'workorder:view', 'workorder:create', 'workorder:update', 'workorder:delete',
            'workorder:assign', 'workorder:start', 'workorder:log_time', 'workorder:use_parts',
            'workorder:add_attachments', 'workorder:comment', 'workorder:close', 'workorder:reopen',
            'workorder:approve_close',
            // Parts
            'part:view_any', 'part:view', 'part:create', 'part:update', 'part:delete',
            'part:link_to_equipment', 'part:unlink_from_equipment', 'part:link_to_workorder',
            'part:manage_minmax', 'part:manage_suppliers',
            // Stock
            'stock:view_any', 'stock:view', 'stock:receive', 'stock:issue', 'stock:transfer',
            'stock:adjust', 'stock:inventory', 'stock:view_valuation',
            // Intervention Requests
            'intervention_request:view_any', 'intervention_request:view', 'intervention_request:create',
            'intervention_request:update', 'intervention_request:delete', 'intervention_request:validate',
            'intervention_request:convert',
            // Preventive Maintenance
            'preventive:view_any', 'preventive:view', 'preventive:create', 'preventive:update',
            'preventive:delete', 'preventive:generate_wo',
            // Transport complet
            'driver:view_any', 'driver:view', 'driver:create', 'driver:update', 'driver:delete',
            'truck:view_any', 'truck:view', 'truck:create', 'truck:update', 'truck:delete',
            'assignment:view_any', 'assignment:view', 'assignment:create', 'assignment:update', 'assignment:delete',
            'client:view_any', 'client:view', 'client:create', 'client:update', 'client:delete',
            'habilitation:view_any', 'habilitation:view', 'habilitation:create', 'habilitation:update', 'habilitation:delete',
            // Reports
            'report:view_any', 'report:view_kpi', 'report:export',
            // ✅ Settings (lecture + modification)
            'setting:view_any', 'setting:view', 'setting:update',
        ]);

        // 3. Planificateur
        $planificateur = Role::firstOrCreate(['name' => 'Planificateur', 'guard_name' => 'web']);
        $planificateur->syncPermissions([
            'equipment:view_any', 'equipment:view',
            'location:view_any', 'location:view',
            'workorder_request:view_any', 'workorder_request:view', 'workorder_request:approve',
            'workorder:view_any', 'workorder:view', 'workorder:create', 'workorder:update',
            'workorder:assign', 'workorder:log_time', 'workorder:use_parts', 'workorder:close', 'workorder:reopen',
            'part:view_any', 'part:view', 'part:link_to_workorder',
            'stock:view_any', 'stock:view',
            'intervention_request:view_any', 'intervention_request:view', 'intervention_request:create',
            'intervention_request:validate', 'intervention_request:convert',
            'preventive:view_any', 'preventive:view', 'preventive:create', 'preventive:update', 'preventive:generate_wo',
            // Transport (lecture + gestion)
            'driver:view_any', 'driver:view', 'driver:create', 'driver:update',
            'truck:view_any', 'truck:view', 'truck:create', 'truck:update',
            'assignment:view_any', 'assignment:view', 'assignment:create', 'assignment:update',
            'client:view_any', 'client:view',
            'habilitation:view_any', 'habilitation:view',
            'report:view_any', 'report:view_kpi',
        ]);

        // 4. Technicien
        $technicien = Role::firstOrCreate(['name' => 'Technicien', 'guard_name' => 'web']);
        $technicien->syncPermissions([
            'equipment:view_any', 'equipment:view',
            'location:view_any', 'location:view',
            'workorder_request:view_own', 'workorder_request:create',
            'workorder:view_own', 'workorder:view', 'workorder:start', 'workorder:log_time',
            'workorder:use_parts', 'workorder:add_attachments', 'workorder:comment', 'workorder:close',
            'part:view_any', 'part:view',
            'stock:view', 'stock:issue',
            'intervention_request:view_any', 'intervention_request:view', 'intervention_request:create',
            'preventive:view_any', 'preventive:view',
            // Transport (lecture)
            'driver:view_any', 'driver:view',
            'truck:view_any', 'truck:view',
            'assignment:view_any', 'assignment:view',
            'client:view_any', 'client:view',
            'habilitation:view_any', 'habilitation:view',
        ]);

        // 5. Magasinier
        $magasinier = Role::firstOrCreate(['name' => 'Magasinier', 'guard_name' => 'web']);
        $magasinier->syncPermissions([
            'workorder:view_any', 'workorder:view',
            'part:view_any', 'part:view', 'part:create', 'part:update',
            'part:link_to_equipment', 'part:unlink_from_equipment', 'part:manage_minmax', 'part:manage_suppliers',
            'stock:view_any', 'stock:view', 'stock:receive', 'stock:issue', 'stock:transfer',
            'stock:adjust', 'stock:inventory',
            'intervention_request:view_any', 'intervention_request:view', 'intervention_request:create',
            'intervention_request:update', 'intervention_request:delete', 'intervention_request:validate',
            'intervention_request:convert',
            'report:view_kpi',
        ]);

        // 6. Lecteur
        $lecteur = Role::firstOrCreate(['name' => 'Lecteur', 'guard_name' => 'web']);
        $lecteur->syncPermissions([
            'site:view_any', 'site:view',
            'equipment:view_any', 'equipment:view',
            'location:view_any', 'location:view',
            'workorder_request:view_any', 'workorder_request:view',
            'workorder:view_any', 'workorder:view',
            'part:view_any', 'part:view',
            'stock:view_any', 'stock:view',
            'intervention_request:view_any', 'intervention_request:view',
            'preventive:view_any', 'preventive:view',
            // Transport (lecture)
            'driver:view_any', 'driver:view',
            'truck:view_any', 'truck:view',
            'assignment:view_any', 'assignment:view',
            'client:view_any', 'client:view',
            'habilitation:view_any', 'habilitation:view',
            'report:view_any', 'report:view_kpi',
        ]);
    }
}
