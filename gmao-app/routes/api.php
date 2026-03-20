<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EquipmentController;
use App\Http\Controllers\Api\LocationController;
use App\Http\Controllers\Api\PartController;
use App\Http\Controllers\Api\SiteController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkOrderController;
use App\Http\Controllers\Api\InterventionRequestController;
use App\Http\Controllers\Api\PreventiveMaintenanceController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ClientController;
use App\Http\Controllers\Api\HabilitationController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\TruckController;
use App\Http\Controllers\Api\TruckDriverHistoryController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SettingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes publiques
|--------------------------------------------------------------------------
*/

Route::post('/login', [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Routes protégées
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Sites
    Route::get('/sites-list', [SiteController::class, 'list']);
    Route::get('/sites/stats', [SiteController::class, 'stats']);
    Route::get('/sites/nearby', [SiteController::class, 'nearby']);
    Route::get('/sites/export', [SiteController::class, 'export']);
    Route::post('/sites/import', [SiteController::class, 'import']);
    Route::post('/sites/{site}/toggle-active', [SiteController::class, 'toggleActive']);
    Route::apiResource('sites', SiteController::class);
    Route::get('/my-sites', [SiteController::class, 'mySites']);
    Route::post('/switch-site/{site}', [SiteController::class, 'switchSite']);

    // Emplacements
    Route::get('/locations', [LocationController::class, 'index']);
    Route::get('/locations-list', [LocationController::class, 'list']);
    Route::get('/locations-tree', [LocationController::class, 'tree']);
    Route::post('/locations', [LocationController::class, 'store']);
    Route::get('/locations/{location}', [LocationController::class, 'show']);
    Route::put('/locations/{location}', [LocationController::class, 'update']);
    Route::delete('/locations/{location}', [LocationController::class, 'destroy']);

    // Equipments
    // ⚠️ Les routes nommées (export, import) doivent être déclarées AVANT
    //    apiResource pour ne pas être interceptées par show({equipment}).
    Route::get('/equipments/export', [EquipmentController::class, 'export']);   // ← EXPORT
    Route::post('/equipments/import', [EquipmentController::class, 'import']);  // ← IMPORT
    Route::apiResource('equipments', EquipmentController::class);

    // Parts (Pièces)
    Route::post('/parts/{part}/adjust-stock', [PartController::class, 'adjustStock']);
    Route::apiResource('parts', PartController::class);

    // Work Orders (OT)
    Route::get('/work-orders/stats', [WorkOrderController::class, 'stats']);
    Route::post('/work-orders/{workOrder}/status', [WorkOrderController::class, 'updateStatus']);
    Route::post('/work-orders/{workOrder}/start', [WorkOrderController::class, 'start']);
    Route::post('/work-orders/{workOrder}/pause', [WorkOrderController::class, 'pause']);
    Route::post('/work-orders/{workOrder}/resume', [WorkOrderController::class, 'resume']);
    Route::post('/work-orders/{workOrder}/complete', [WorkOrderController::class, 'complete']);
    Route::post('/work-orders/{workOrder}/cancel', [WorkOrderController::class, 'cancel']);
    Route::post('/work-orders/{workOrder}/assign', [WorkOrderController::class, 'assign']);
    Route::get('/work-orders/{workOrder}/available-parts', [WorkOrderController::class, 'availableParts']);
    Route::post('/work-orders/{workOrder}/parts', [WorkOrderController::class, 'addPart']);
    Route::delete('/work-orders/{workOrder}/parts/{part}', [WorkOrderController::class, 'removePart']);
    Route::post('/work-orders/{workOrder}/comments', [WorkOrderController::class, 'addComment']);
    Route::get('/equipments/{equipment}/work-orders', [WorkOrderController::class, 'forEquipment']);
    Route::get('/trucks/{truck}/work-orders', [WorkOrderController::class, 'forTruck']);
    Route::apiResource('work-orders', WorkOrderController::class);

    // Users (Utilisateurs)
    Route::get('/user-roles', [UserController::class, 'getRoles']);
    Route::post('/users/{user}/roles', [UserController::class, 'updateRoles']);
    Route::post('/users/{user}/change-password', [UserController::class, 'changePassword']);
    Route::apiResource('users', UserController::class);
    Route::put('/users/{user}/sites', [UserController::class, 'updateSites']);

    // Rôles & Permissions
    Route::get('/permissions', [RoleController::class, 'permissions']);
    Route::apiResource('roles', RoleController::class);

    // Intervention Requests (DI)
    Route::get('/intervention-requests/stats', [InterventionRequestController::class, 'stats']);
    Route::post('/intervention-requests/{interventionRequest}/approve', [InterventionRequestController::class, 'approve']);
    Route::post('/intervention-requests/{interventionRequest}/reject', [InterventionRequestController::class, 'reject']);
    Route::post('/intervention-requests/{interventionRequest}/validate', [InterventionRequestController::class, 'validate']);
    Route::post('/intervention-requests/{interventionRequest}/convert', [InterventionRequestController::class, 'convertToWorkOrder']);
    Route::post('/intervention-requests/{interventionRequest}/cancel', [InterventionRequestController::class, 'cancel']);
    Route::get('/equipments/{equipment}/intervention-requests', [InterventionRequestController::class, 'forEquipment']);
    Route::get('/trucks/{truck}/intervention-requests', [InterventionRequestController::class, 'forTruck']);
    Route::apiResource('intervention-requests', InterventionRequestController::class);

    // Preventive Maintenance (MP)
    Route::get('/preventive-maintenances/stats', [PreventiveMaintenanceController::class, 'stats']);
    Route::get('/preventive-maintenances/calendar', [PreventiveMaintenanceController::class, 'calendar']);
    Route::get('/preventive-maintenances/upcoming', [PreventiveMaintenanceController::class, 'upcoming']);
    Route::post('/preventive-maintenances/check-generate', [PreventiveMaintenanceController::class, 'checkAndGenerate']);
    Route::post('/preventive-maintenances/{preventiveMaintenance}/toggle-active', [PreventiveMaintenanceController::class, 'toggleActive']);
    Route::post('/preventive-maintenances/{preventiveMaintenance}/generate', [PreventiveMaintenanceController::class, 'generateWorkOrder']);
    Route::get('/equipments/{equipment}/preventive-maintenances', [PreventiveMaintenanceController::class, 'forEquipment']);
    Route::get('/trucks/{truck}/preventive-maintenances', [PreventiveMaintenanceController::class, 'forTruck']);
    Route::apiResource('preventive-maintenances', PreventiveMaintenanceController::class);

    // Génération manuelle des OT préventifs (admin uniquement)
    Route::post('/preventive-maintenances/generate-all', function (Request $request) {
        if (!$request->user()->hasRole('SuperAdmin')) {
            abort(403, 'Accès réservé aux super administrateurs');
        }

        \Illuminate\Support\Facades\Artisan::call('maintenance:generate-preventive', [
            '--site' => $request->user()->current_site_id,
        ]);

        return response()->json([
            'message' => 'Génération terminée',
            'output'  => \Illuminate\Support\Facades\Artisan::output(),
        ]);
    });

    // Reports
    Route::prefix('reports')->group(function () {
        Route::get('/kpis', [ReportController::class, 'kpis']);
        Route::get('/work-orders/trend', [ReportController::class, 'workOrderTrend']);
        Route::get('/work-orders/by-type', [ReportController::class, 'workOrdersByType']);
        Route::get('/work-orders/by-status', [ReportController::class, 'workOrdersByStatus']);
        Route::get('/work-orders/export', [ReportController::class, 'exportWorkOrders']);
        Route::get('/equipments/top-failures', [ReportController::class, 'topEquipmentsByFailures']);
        Route::get('/equipments/costs', [ReportController::class, 'costsByEquipment']);
        Route::get('/costs/trend', [ReportController::class, 'costsTrend']);
        Route::get('/technicians/performance', [ReportController::class, 'technicianPerformance']);
        Route::get('/stock/critical', [ReportController::class, 'criticalStock']);
        Route::get('/parts/consumption', [ReportController::class, 'partsConsumption']);
    });

    // Notifications
    Route::prefix('notifications')->group(function () {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('/unread-count', [NotificationController::class, 'unreadCount']);
        Route::post('/mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::post('/clear-read', [NotificationController::class, 'clearRead']);
        Route::post('/generate', [NotificationController::class, 'generate']);
        Route::post('/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::delete('/{notification}', [NotificationController::class, 'destroy']);
    });

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::post('/dashboard/refresh', [DashboardController::class, 'refresh']);

    // Clients
    Route::apiResource('clients', ClientController::class);
    Route::get('clients-list', [ClientController::class, 'list']);

    // Habilitations
    Route::apiResource('habilitations', HabilitationController::class);
    Route::get('habilitations-list', [HabilitationController::class, 'list']);
    Route::get('habilitations-categories', [HabilitationController::class, 'categories']);

    // Chauffeurs
    Route::apiResource('drivers', DriverController::class);
    Route::get('drivers-list', [DriverController::class, 'list']);
    Route::post('drivers/{driver}/habilitations', [DriverController::class, 'addHabilitation']);
    Route::delete('drivers/{driver}/habilitations/{habilitation}', [DriverController::class, 'removeHabilitation']);
    Route::post('drivers/{driver}/check-eligibility', [DriverController::class, 'checkClientEligibility']);
    Route::get('drivers-expiring-habilitations', [DriverController::class, 'expiringHabilitations']);
    Route::get('drivers-expired-habilitations', [DriverController::class, 'expiredHabilitations']);

    // Camions
    Route::get('trucks/export', [TruckController::class, 'export']);
    Route::apiResource('trucks', TruckController::class);
    Route::post('trucks/import', [TruckController::class, 'import']);
    Route::get('trucks-list', [TruckController::class, 'list']);
    Route::post('trucks/{truck}/assign-driver', [TruckController::class, 'assignDriver']);
    Route::patch('trucks/{truck}/mileage', [TruckController::class, 'updateMileage']);
    Route::get('trucks-alerts', [TruckController::class, 'alerts']);
    Route::get('trucks-types', [TruckController::class, 'types']);

    // Historique attributions camions/chauffeurs
    Route::prefix('assignments')->group(function () {
        Route::get('/', [TruckDriverHistoryController::class, 'index']);
        Route::get('/active', [TruckDriverHistoryController::class, 'activeAssignments']);
        Route::get('/stats', [TruckDriverHistoryController::class, 'stats']);
        Route::post('/assign', [TruckDriverHistoryController::class, 'assign']);
        Route::post('/{history}/unassign', [TruckDriverHistoryController::class, 'unassign']);
    });

    Route::get('/trucks/{truck}/history', [TruckDriverHistoryController::class, 'truckHistory']);
    Route::get('/drivers/{driver}/history', [TruckDriverHistoryController::class, 'driverHistory']);

    // Paramètres
    Route::prefix('settings')->group(function () {
        Route::get('/', [SettingController::class, 'index']);
        Route::get('/public', [SettingController::class, 'publicSettings']);
        Route::get('/group/{group}', [SettingController::class, 'group']);
        Route::get('/options/{group}/{key}', [SettingController::class, 'options']);
        Route::post('/', [SettingController::class, 'store']);
        Route::put('/bulk', [SettingController::class, 'bulkUpdate']);
        Route::put('/{setting}', [SettingController::class, 'update']);
        Route::delete('/{setting}', [SettingController::class, 'destroy']);
        Route::post('/reset/{group}', [SettingController::class, 'resetGroup']);
    });
});