<?php

namespace App\Services;

use App\Models\Equipment;
use App\Models\Notification;
use App\Models\Part;
use App\Models\PreventiveMaintenance;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;

class NotificationService
{
    /**
     * Générer toutes les notifications automatiques pour un site
     */
    public function generateForSite(Site $site): array
    {
        $generated = [];

        $generated['stock_critical'] = $this->checkCriticalStock($site);
        $generated['wo_overdue'] = $this->checkOverdueWorkOrders($site);
        $generated['pm_upcoming'] = $this->checkUpcomingPreventive($site);
        $generated['equipment_down'] = $this->checkEquipmentDown($site);

        return $generated;
    }

    /**
     * Vérifier le stock critique
     */
    public function checkCriticalStock(Site $site): int
    {
        $criticalParts = Part::where('site_id', $site->id)
            ->where('is_active', true)
            ->whereRaw('quantity_in_stock <= minimum_stock')
            ->get();

        $count = 0;
        foreach ($criticalParts as $part) {
            // Éviter les doublons - vérifier si notification existe déjà (non lue, dernières 24h)
            $exists = Notification::where('site_id', $site->id)
                ->where('type', Notification::TYPE_STOCK_CRITICAL)
                ->where('reference_type', Part::class)
                ->where('reference_id', $part->id)
                ->where('created_at', '>=', now()->subHours(24))
                ->exists();

            if (!$exists) {
                Notification::create([
                    'site_id' => $site->id,
                    'user_id' => null, // Pour tous les utilisateurs
                    'type' => Notification::TYPE_STOCK_CRITICAL,
                    'title' => 'Stock critique',
                    'message' => "La pièce \"{$part->name}\" est en stock critique ({$part->quantity_in_stock} {$part->unit} / min: {$part->minimum_stock})",
                    'icon' => '⚠️',
                    'color' => 'danger',
                    'link' => "/parts/{$part->id}",
                    'reference_type' => Part::class,
                    'reference_id' => $part->id,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Vérifier les OT en retard
     */
    public function checkOverdueWorkOrders(Site $site): int
    {
        $overdueWOs = WorkOrder::where('site_id', $site->id)
            ->whereIn('status', ['pending', 'approved', 'in_progress', 'on_hold'])
            ->where(function ($query) {
                $query->where('scheduled_end', '<', now())
                    ->orWhere('due_date', '<', now());
            })
            ->get();

        $count = 0;
        foreach ($overdueWOs as $wo) {
            $exists = Notification::where('site_id', $site->id)
                ->where('type', Notification::TYPE_WO_OVERDUE)
                ->where('reference_type', WorkOrder::class)
                ->where('reference_id', $wo->id)
                ->where('created_at', '>=', now()->subHours(24))
                ->exists();

            if (!$exists) {
                $dueDate = $wo->scheduled_end ?? $wo->due_date;
                $daysLate = Carbon::parse($dueDate)->diffInDays(now());

                Notification::create([
                    'site_id' => $site->id,
                    'user_id' => $wo->assigned_to, // Pour le technicien assigné
                    'type' => Notification::TYPE_WO_OVERDUE,
                    'title' => 'OT en retard',
                    'message' => "L'ordre de travail \"{$wo->code}\" est en retard de {$daysLate} jour(s)",
                    'icon' => '🚨',
                    'color' => 'danger',
                    'link' => "/work-orders/{$wo->id}",
                    'reference_type' => WorkOrder::class,
                    'reference_id' => $wo->id,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Vérifier les maintenances préventives à venir
     */
    public function checkUpcomingPreventive(Site $site): int
    {
        $upcomingPMs = PreventiveMaintenance::where('site_id', $site->id)
            ->where('is_active', true)
            ->whereBetween('next_due_date', [now(), now()->addDays(7)])
            ->get();

        $count = 0;
        foreach ($upcomingPMs as $pm) {
            $exists = Notification::where('site_id', $site->id)
                ->where('type', Notification::TYPE_PM_UPCOMING)
                ->where('reference_type', PreventiveMaintenance::class)
                ->where('reference_id', $pm->id)
                ->where('created_at', '>=', now()->subDays(3))
                ->exists();

            if (!$exists) {
                $daysUntil = now()->diffInDays($pm->next_due_date);
                $message = $daysUntil == 0 
                    ? "Maintenance préventive \"{$pm->title}\" prévue aujourd'hui"
                    : "Maintenance préventive \"{$pm->title}\" prévue dans {$daysUntil} jour(s)";

                Notification::create([
                    'site_id' => $site->id,
                    'user_id' => null,
                    'type' => Notification::TYPE_PM_UPCOMING,
                    'title' => 'Maintenance à venir',
                    'message' => $message,
                    'icon' => '📅',
                    'color' => 'warning',
                    'link' => "/preventive-maintenance/{$pm->id}",
                    'reference_type' => PreventiveMaintenance::class,
                    'reference_id' => $pm->id,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Vérifier les équipements en panne
     */
    public function checkEquipmentDown(Site $site): int
    {
        $downEquipments = Equipment::where('site_id', $site->id)
            ->whereIn('status', ['stopped', 'broken'])
            ->get();

        $count = 0;
        foreach ($downEquipments as $equipment) {
            $exists = Notification::where('site_id', $site->id)
                ->where('type', Notification::TYPE_EQUIPMENT_DOWN)
                ->where('reference_type', Equipment::class)
                ->where('reference_id', $equipment->id)
                ->whereNull('read_at')
                ->exists();

            if (!$exists) {
                Notification::create([
                    'site_id' => $site->id,
                    'user_id' => null,
                    'type' => Notification::TYPE_EQUIPMENT_DOWN,
                    'title' => 'Équipement en panne',
                    'message' => "L'équipement \"{$equipment->name}\" ({$equipment->code}) est actuellement {$equipment->status}",
                    'icon' => '🔴',
                    'color' => 'danger',
                    'link' => "/equipments/{$equipment->id}",
                    'reference_type' => Equipment::class,
                    'reference_id' => $equipment->id,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Créer une notification pour un OT assigné
     */
    public function notifyWorkOrderAssigned(WorkOrder $workOrder): void
    {
        if (!$workOrder->assigned_to) return;

        Notification::create([
            'site_id' => $workOrder->site_id,
            'user_id' => $workOrder->assigned_to,
            'type' => Notification::TYPE_WO_ASSIGNED,
            'title' => 'Nouvel OT assigné',
            'message' => "L'ordre de travail \"{$workOrder->code}\" vous a été assigné",
            'icon' => '📋',
            'color' => 'info',
            'link' => "/work-orders/{$workOrder->id}",
            'reference_type' => WorkOrder::class,
            'reference_id' => $workOrder->id,
        ]);
    }

    /**
     * Créer une notification pour un OT terminé
     */
    public function notifyWorkOrderCompleted(WorkOrder $workOrder): void
    {
        Notification::create([
            'site_id' => $workOrder->site_id,
            'user_id' => $workOrder->created_by,
            'type' => Notification::TYPE_WO_COMPLETED,
            'title' => 'OT terminé',
            'message' => "L'ordre de travail \"{$workOrder->code}\" a été terminé",
            'icon' => '✅',
            'color' => 'success',
            'link' => "/work-orders/{$workOrder->id}",
            'reference_type' => WorkOrder::class,
            'reference_id' => $workOrder->id,
        ]);
    }
}
