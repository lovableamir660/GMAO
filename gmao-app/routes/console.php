<?php

use Illuminate\Support\Facades\Schedule;

// Génération automatique des OT préventifs - tous les jours à 6h00
Schedule::command('maintenance:generate-preventive')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->appendOutputTo(storage_path('logs/preventive-generation.log'));

// Alternative: toutes les heures (pour tests ou besoins plus fréquents)
// Schedule::command('maintenance:generate-preventive')->hourly();

// Rappels de maintenance - tous les jours à 8h00
Schedule::command('maintenance:send-reminders')
    ->dailyAt('08:00')
    ->appendOutputTo(storage_path('logs/maintenance-reminders.log'));
