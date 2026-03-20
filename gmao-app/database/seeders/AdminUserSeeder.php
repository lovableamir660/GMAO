<?php

namespace Database\Seeders;

use App\Models\Equipment;
use App\Models\Location;
use App\Models\Part;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        // Créer les sites
        $sitePrincipal = Site::create([
            'name' => 'Usine M\'Sila',
            'code' => 'SITE-001',
            'address' => 'Zone Industrielle',
            'city' => 'M\'Sila',
            'country' => 'Algérie',
            'phone' => '+213 35 XX XX XX',
            'email' => 'contact@usine-msila.dz',
            'is_active' => true,
        ]);

        $siteSecondaire = Site::create([
            'name' => 'Dépôt Alger',
            'code' => 'SITE-002',
            'address' => 'Route Nationale',
            'city' => 'Alger',
            'country' => 'Algérie',
            'phone' => '+213 21 XX XX XX',
            'email' => 'contact@depot-alger.dz',
            'is_active' => true,
        ]);

        // Définir le site pour Spatie
        app()[PermissionRegistrar::class]->setPermissionsTeamId($sitePrincipal->id);

        // Créer les utilisateurs
        $superAdmin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@gmao.com',
            'password' => Hash::make('password'),
            'current_site_id' => $sitePrincipal->id,
        ]);
        $superAdmin->assignRole('SuperAdmin');

        $adminSite = User::create([
            'name' => 'Ahmed Benali',
            'email' => 'ahmed@gmao.com',
            'password' => Hash::make('password'),
            'current_site_id' => $sitePrincipal->id,
        ]);
        $adminSite->assignRole('AdminSite');

        $technicien1 = User::create([
            'name' => 'Karim Technicien',
            'email' => 'karim@gmao.com',
            'password' => Hash::make('password'),
            'current_site_id' => $sitePrincipal->id,
        ]);
        $technicien1->assignRole('Technicien');

        $technicien2 = User::create([
            'name' => 'Youcef Mécanicien',
            'email' => 'youcef@gmao.com',
            'password' => Hash::make('password'),
            'current_site_id' => $sitePrincipal->id,
        ]);
        $technicien2->assignRole('Technicien');

        $magasinier = User::create([
            'name' => 'Samir Magasinier',
            'email' => 'samir@gmao.com',
            'password' => Hash::make('password'),
            'current_site_id' => $sitePrincipal->id,
        ]);
        $magasinier->assignRole('Magasinier');

        // Créer les emplacements
        $atelierMeca = Location::create([
            'site_id' => $sitePrincipal->id,
            'name' => 'Atelier Mécanique',
            'code' => 'ATL-MECA',
            'is_active' => true,
        ]);

        $atelierElec = Location::create([
            'site_id' => $sitePrincipal->id,
            'name' => 'Atelier Électrique',
            'code' => 'ATL-ELEC',
            'is_active' => true,
        ]);

        $ligneProduction = Location::create([
            'site_id' => $sitePrincipal->id,
            'name' => 'Ligne de Production',
            'code' => 'LIGNE-01',
            'is_active' => true,
        ]);

        $utilites = Location::create([
            'site_id' => $sitePrincipal->id,
            'name' => 'Utilités',
            'code' => 'UTIL',
            'is_active' => true,
        ]);

        // Créer les équipements
        $compresseur = Equipment::create([
            'site_id' => $sitePrincipal->id,
            'location_id' => $utilites->id,
            'code' => 'COMP-001',
            'name' => 'Compresseur Atlas Copco GA37',
            'type' => 'Compresseur',
            'brand' => 'Atlas Copco',
            'model' => 'GA37',
            'serial_number' => 'AII-123456',
            'criticality' => 'critical',
            'status' => 'operational',
            'installation_date' => '2020-03-15',
        ]);

        $pompe1 = Equipment::create([
            'site_id' => $sitePrincipal->id,
            'location_id' => $utilites->id,
            'code' => 'PUMP-001',
            'name' => 'Pompe centrifuge Grundfos',
            'type' => 'Pompe',
            'brand' => 'Grundfos',
            'model' => 'CR 32-2',
            'criticality' => 'high',
            'status' => 'operational',
            'installation_date' => '2019-07-20',
        ]);

        $moteur1 = Equipment::create([
            'site_id' => $sitePrincipal->id,
            'location_id' => $ligneProduction->id,
            'code' => 'MOT-001',
            'name' => 'Moteur principal convoyeur',
            'type' => 'Moteur électrique',
            'brand' => 'Siemens',
            'model' => '1LA7163-4AA',
            'criticality' => 'critical',
            'status' => 'operational',
            'installation_date' => '2018-01-10',
        ]);

        $convoyeur = Equipment::create([
            'site_id' => $sitePrincipal->id,
            'location_id' => $ligneProduction->id,
            'code' => 'CONV-001',
            'name' => 'Convoyeur à bande principal',
            'type' => 'Convoyeur',
            'brand' => 'Interroll',
            'criticality' => 'high',
            'status' => 'degraded',
            'installation_date' => '2017-05-22',
        ]);

        $groupeElectro = Equipment::create([
            'site_id' => $sitePrincipal->id,
            'location_id' => $utilites->id,
            'code' => 'GE-001',
            'name' => 'Groupe électrogène Caterpillar',
            'type' => 'Groupe électrogène',
            'brand' => 'Caterpillar',
            'model' => 'C18',
            'criticality' => 'critical',
            'status' => 'operational',
            'installation_date' => '2021-09-01',
        ]);

        $tour = Equipment::create([
            'site_id' => $sitePrincipal->id,
            'location_id' => $atelierMeca->id,
            'code' => 'TOUR-001',
            'name' => 'Tour conventionnel',
            'type' => 'Machine-outil',
            'brand' => 'Cazeneuve',
            'criticality' => 'medium',
            'status' => 'maintenance',
            'installation_date' => '2015-03-10',
        ]);

        // Créer les pièces
        $filtre = Part::create([
            'site_id' => $sitePrincipal->id,
            'code' => 'FLT-001',
            'name' => 'Filtre à air compresseur',
            'category' => 'Filtration',
            'unit' => 'unité',
            'unit_price' => 4500,
            'quantity_in_stock' => 5,
            'minimum_stock' => 2,
            'location_in_warehouse' => 'Étagère A1',
            'manufacturer' => 'Atlas Copco',
        ]);

        $courroie = Part::create([
            'site_id' => $sitePrincipal->id,
            'code' => 'CRR-001',
            'name' => 'Courroie trapézoïdale SPB 2360',
            'category' => 'Transmission',
            'unit' => 'unité',
            'unit_price' => 2800,
            'quantity_in_stock' => 8,
            'minimum_stock' => 4,
            'location_in_warehouse' => 'Étagère B2',
            'manufacturer' => 'Gates',
        ]);

        $roulement = Part::create([
            'site_id' => $sitePrincipal->id,
            'code' => 'RLT-001',
            'name' => 'Roulement SKF 6205-2RS',
            'category' => 'Roulements',
            'unit' => 'unité',
            'unit_price' => 1200,
            'quantity_in_stock' => 15,
            'minimum_stock' => 5,
            'location_in_warehouse' => 'Étagère C1',
            'manufacturer' => 'SKF',
        ]);

        $huile = Part::create([
            'site_id' => $sitePrincipal->id,
            'code' => 'HLE-001',
            'name' => 'Huile compresseur Roto-Inject',
            'category' => 'Lubrifiants',
            'unit' => 'litre',
            'unit_price' => 3500,
            'quantity_in_stock' => 20,
            'minimum_stock' => 10,
            'location_in_warehouse' => 'Zone huiles',
            'manufacturer' => 'Atlas Copco',
        ]);

        $joint = Part::create([
            'site_id' => $sitePrincipal->id,
            'code' => 'JNT-001',
            'name' => 'Joint mécanique pompe',
            'category' => 'Étanchéité',
            'unit' => 'unité',
            'unit_price' => 8500,
            'quantity_in_stock' => 2,
            'minimum_stock' => 2,
            'location_in_warehouse' => 'Étagère D3',
            'manufacturer' => 'Grundfos',
        ]);

        $fusible = Part::create([
            'site_id' => $sitePrincipal->id,
            'code' => 'FUS-001',
            'name' => 'Fusible industriel 63A',
            'category' => 'Électrique',
            'unit' => 'unité',
            'unit_price' => 850,
            'quantity_in_stock' => 1,
            'minimum_stock' => 5,
            'location_in_warehouse' => 'Armoire élec.',
            'manufacturer' => 'Schneider',
        ]);

        // Créer les interventions
        WorkOrder::create([
            'site_id' => $sitePrincipal->id,
            'equipment_id' => $compresseur->id,
            'requested_by' => $adminSite->id,
            'assigned_to' => $technicien1->id,
            'code' => 'OT-2026-0001',
            'title' => 'Maintenance préventive compresseur',
            'description' => 'Vidange huile, remplacement filtres, contrôle général',
            'type' => 'preventive',
            'priority' => 'medium',
            'status' => 'in_progress',
            'scheduled_start' => now(),
            'actual_start' => now(),
        ]);

        WorkOrder::create([
            'site_id' => $sitePrincipal->id,
            'equipment_id' => $convoyeur->id,
            'requested_by' => $technicien2->id,
            'assigned_to' => $technicien1->id,
            'code' => 'OT-2026-0002',
            'title' => 'Remplacement courroie usée',
            'description' => 'La courroie présente des signes d\'usure avancée, vibrations anormales',
            'type' => 'corrective',
            'priority' => 'high',
            'status' => 'pending',
        ]);

        WorkOrder::create([
            'site_id' => $sitePrincipal->id,
            'equipment_id' => $pompe1->id,
            'requested_by' => $adminSite->id,
            'assigned_to' => $technicien2->id,
            'code' => 'OT-2026-0003',
            'title' => 'Fuite sur garniture mécanique',
            'description' => 'Légère fuite détectée au niveau du joint mécanique',
            'type' => 'corrective',
            'priority' => 'urgent',
            'status' => 'approved',
        ]);

        WorkOrder::create([
            'site_id' => $sitePrincipal->id,
            'equipment_id' => $groupeElectro->id,
            'requested_by' => $adminSite->id,
            'code' => 'OT-2026-0004',
            'title' => 'Test mensuel groupe électrogène',
            'description' => 'Test de démarrage et fonctionnement pendant 15 minutes',
            'type' => 'inspection',
            'priority' => 'low',
            'status' => 'completed',
            'actual_start' => now()->subDays(2),
            'actual_end' => now()->subDays(2)->addMinutes(30),
            'completed_by' => $technicien1->id,
            'completed_at' => now()->subDays(2),
            'work_performed' => 'Test effectué avec succès, RAS',
        ]);

        WorkOrder::create([
            'site_id' => $sitePrincipal->id,
            'equipment_id' => $tour->id,
            'requested_by' => $technicien1->id,
            'assigned_to' => $technicien1->id,
            'code' => 'OT-2026-0005',
            'title' => 'Révision générale tour',
            'description' => 'Remplacement des roulements de broche, réglage des jeux',
            'type' => 'preventive',
            'priority' => 'medium',
            'status' => 'in_progress',
            'actual_start' => now()->subDay(),
        ]);
    }
}
