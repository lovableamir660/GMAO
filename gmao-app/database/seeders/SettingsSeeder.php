<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            'general',
            'equipment',
            'work_order',
            'intervention_request',
            'preventive_maintenance',
            'truck',
            'driver',
            'assignment',
            'habilitation',
            'part',
            'notification',
        ];

        foreach ($groups as $group) {
            $this->seedGroup($group);
        }
    }

    public function seedGroup(string $group): void
    {
        $settings = $this->getDefaults($group);

        foreach ($settings as $setting) {
            $value = $setting['value'];
            if (in_array($setting['type'], ['json', 'list']) && is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            if ($setting['type'] === 'boolean') {
                $value = $value ? '1' : '0';
            }

            Setting::updateOrCreate(
                ['group' => $group, 'key' => $setting['key']],
                [
                    'value' => $value,
                    'type' => $setting['type'],
                    'label' => $setting['label'],
                    'description' => $setting['description'] ?? null,
                    'is_system' => $setting['is_system'] ?? false,
                    'sort_order' => $setting['sort_order'] ?? 0,
                ]
            );
        }
    }

    private function getDefaults(string $group): array
    {
        return match ($group) {
            'general' => [
                [
                    'key' => 'company_name',
                    'value' => 'Mon Entreprise',
                    'type' => 'string',
                    'label' => 'Nom de l\'entreprise',
                    'description' => 'Affiché dans les rapports et l\'en-tête',
                    'is_system' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'company_address',
                    'value' => '',
                    'type' => 'string',
                    'label' => 'Adresse de l\'entreprise',
                    'sort_order' => 2,
                ],
                [
                    'key' => 'company_phone',
                    'value' => '',
                    'type' => 'string',
                    'label' => 'Téléphone',
                    'sort_order' => 3,
                ],
                [
                    'key' => 'company_email',
                    'value' => '',
                    'type' => 'string',
                    'label' => 'Email de contact',
                    'sort_order' => 4,
                ],
                [
                    'key' => 'pagination_default',
                    'value' => 20,
                    'type' => 'integer',
                    'label' => 'Éléments par page (défaut)',
                    'description' => 'Nombre d\'éléments affichés par défaut dans les tableaux',
                    'is_system' => true,
                    'sort_order' => 5,
                ],
                [
                    'key' => 'currency',
                    'value' => 'DZD',
                    'type' => 'string',
                    'label' => 'Devise',
                    'sort_order' => 6,
                ],
                [
                    'key' => 'date_format',
                    'value' => 'DD/MM/YYYY',
                    'type' => 'string',
                    'label' => 'Format de date',
                    'sort_order' => 7,
                ],
            ],

            'equipment' => [
                [
                    'key' => 'types',
                    'value' => [
                        'pump' => 'Pompe',
                        'compressor' => 'Compresseur',
                        'motor' => 'Moteur',
                        'valve' => 'Vanne',
                        'conveyor' => 'Convoyeur',
                        'hvac' => 'CVC',
                        'electrical' => 'Électrique',
                        'other' => 'Autre',
                    ],
                    'type' => 'json',
                    'label' => 'Types d\'équipement',
                    'description' => 'Liste des types disponibles lors de la création d\'un équipement',
                    'is_system' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'statuses',
                    'value' => [
                        'operational' => 'Opérationnel',
                        'breakdown' => 'En panne',
                        'maintenance' => 'En maintenance',
                        'decommissioned' => 'Déclassé',
                    ],
                    'type' => 'json',
                    'label' => 'Statuts d\'équipement',
                    'is_system' => true,
                    'sort_order' => 2,
                ],
                [
                    'key' => 'criticalities',
                    'value' => [
                        'low' => 'Faible',
                        'medium' => 'Moyenne',
                        'high' => 'Haute',
                        'critical' => 'Critique',
                    ],
                    'type' => 'json',
                    'label' => 'Niveaux de criticité',
                    'is_system' => true,
                    'sort_order' => 3,
                ],
            ],

            'work_order' => [
                [
                    'key' => 'types',
                    'value' => [
                        'corrective' => 'Correctif',
                        'preventive' => 'Préventif',
                        'improvement' => 'Amélioratif',
                        'inspection' => 'Inspection',
                    ],
                    'type' => 'json',
                    'label' => 'Types d\'OT',
                    'is_system' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'priorities',
                    'value' => [
                        'low' => 'Basse',
                        'medium' => 'Moyenne',
                        'high' => 'Haute',
                        'critical' => 'Critique',
                    ],
                    'type' => 'json',
                    'label' => 'Priorités',
                    'is_system' => true,
                    'sort_order' => 2,
                ],
                [
                    'key' => 'auto_generate_code',
                    'value' => true,
                    'type' => 'boolean',
                    'label' => 'Générer automatiquement le code OT',
                    'sort_order' => 3,
                ],
                [
                    'key' => 'code_prefix',
                    'value' => 'OT',
                    'type' => 'string',
                    'label' => 'Préfixe du code OT',
                    'sort_order' => 4,
                ],
            ],

            'intervention_request' => [
                [
                    'key' => 'types',
                    'value' => [
                        'corrective' => 'Correctif',
                        'preventive' => 'Préventif',
                        'improvement' => 'Amélioratif',
                        'inspection' => 'Inspection',
                    ],
                    'type' => 'json',
                    'label' => 'Types de DI',
                    'is_system' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'urgencies',
                    'value' => [
                        'low' => 'Basse',
                        'medium' => 'Moyenne',
                        'high' => 'Haute',
                        'critical' => 'Critique',
                    ],
                    'type' => 'json',
                    'label' => 'Niveaux d\'urgence',
                    'is_system' => true,
                    'sort_order' => 2,
                ],
                [
                    'key' => 'auto_approve',
                    'value' => false,
                    'type' => 'boolean',
                    'label' => 'Approbation automatique des DI',
                    'description' => 'Si activé, les DI sont approuvées automatiquement',
                    'sort_order' => 3,
                ],
                [
                    'key' => 'code_prefix',
                    'value' => 'DI',
                    'type' => 'string',
                    'label' => 'Préfixe du code DI',
                    'sort_order' => 4,
                ],
            ],

            'preventive_maintenance' => [
                [
                    'key' => 'frequencies',
                    'value' => [
                        'daily' => 'Quotidien',
                        'weekly' => 'Hebdomadaire',
                        'biweekly' => 'Bi-hebdomadaire',
                        'monthly' => 'Mensuel',
                        'quarterly' => 'Trimestriel',
                        'biannual' => 'Semestriel',
                        'annual' => 'Annuel',
                    ],
                    'type' => 'json',
                    'label' => 'Fréquences de maintenance',
                    'is_system' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'types',
                    'value' => [
                        'systematic' => 'Systématique',
                        'conditional' => 'Conditionnel',
                        'predictive' => 'Prédictif',
                    ],
                    'type' => 'json',
                    'label' => 'Types de maintenance préventive',
                    'is_system' => true,
                    'sort_order' => 2,
                ],
                [
                    'key' => 'advance_days',
                    'value' => 7,
                    'type' => 'integer',
                    'label' => 'Jours d\'avance pour génération OT',
                    'description' => 'Nombre de jours avant l\'échéance pour générer l\'OT préventif',
                    'sort_order' => 3,
                ],
            ],

            'truck' => [
                [
                    'key' => 'types',
                    'value' => [
                        'flatbed' => 'Plateau',
                        'tanker' => 'Citerne',
                        'refrigerated' => 'Frigorifique',
                        'box' => 'Fourgon',
                        'dump' => 'Benne',
                        'crane' => 'Grue',
                        'tractor' => 'Tracteur',
                        'trailer' => 'Remorque',
                        'other' => 'Autre',
                    ],
                    'type' => 'json',
                    'label' => 'Types de camion',
                    'is_system' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'fuel_types',
                    'value' => [
                        'diesel' => 'Diesel',
                        'gasoline' => 'Essence',
                        'electric' => 'Électrique',
                        'hybrid' => 'Hybride',
                        'lpg' => 'GPL',
                    ],
                    'type' => 'json',
                    'label' => 'Types de carburant',
                    'is_system' => true,
                    'sort_order' => 2,
                ],
                [
                    'key' => 'statuses',
                    'value' => [
                        'available' => 'Disponible',
                        'in_use' => 'En service',
                        'maintenance' => 'En maintenance',
                        'breakdown' => 'En panne',
                        'decommissioned' => 'Déclassé',
                    ],
                    'type' => 'json',
                    'label' => 'Statuts de camion',
                    'is_system' => true,
                    'sort_order' => 3,
                ],
                [
                    'key' => 'mileage_alert_threshold',
                    'value' => 10000,
                    'type' => 'integer',
                    'label' => 'Seuil d\'alerte kilométrage (km)',
                    'description' => 'Alerte si le camion dépasse ce kilométrage sans maintenance',
                    'sort_order' => 4,
                ],
            ],

            'driver' => [
                [
                    'key' => 'license_categories',
                    'value' => [
                        'B' => 'Permis B (Véhicule léger)',
                        'C' => 'Permis C (Poids lourd)',
                        'CE' => 'Permis CE (Super lourd)',
                        'D' => 'Permis D (Transport en commun)',
                    ],
                    'type' => 'json',
                    'label' => 'Catégories de permis',
                    'is_system' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'statuses',
                    'value' => [
                        'active' => 'Actif',
                        'inactive' => 'Inactif',
                        'on_leave' => 'En congé',
                        'suspended' => 'Suspendu',
                    ],
                    'type' => 'json',
                    'label' => 'Statuts de chauffeur',
                    'is_system' => true,
                    'sort_order' => 2,
                ],
            ],

            'assignment' => [
                [
                    'key' => 'assignment_reasons',
                    'value' => [
                        'regular' => 'Attribution régulière',
                        'mission' => 'Mission spécifique',
                        'replacement' => 'Remplacement',
                        'training' => 'Formation',
                        'temporary' => 'Temporaire',
                    ],
                    'type' => 'json',
                    'label' => 'Raisons d\'attribution',
                    'is_system' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'unassignment_reasons',
                    'value' => [
                        'end_mission' => 'Fin de mission',
                        'breakdown' => 'Panne véhicule',
                        'maintenance' => 'Maintenance',
                        'leave' => 'Congé chauffeur',
                        'reassignment' => 'Réaffectation',
                        'termination' => 'Fin de contrat',
                    ],
                    'type' => 'json',
                    'label' => 'Raisons de fin d\'attribution',
                    'is_system' => true,
                    'sort_order' => 2,
                ],
            ],

            'habilitation' => [
                [
                    'key' => 'categories',
                    'value' => [
                        'safety' => 'Sécurité',
                        'technical' => 'Technique',
                        'regulatory' => 'Réglementaire',
                        'client_specific' => 'Spécifique client',
                        'environmental' => 'Environnement',
                    ],
                    'type' => 'json',
                    'label' => 'Catégories d\'habilitation',
                    'is_system' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'expiry_alert_days',
                    'value' => 30,
                    'type' => 'integer',
                    'label' => 'Alerte expiration (jours)',
                    'description' => 'Notifier X jours avant l\'expiration d\'une habilitation',
                    'sort_order' => 2,
                ],
            ],

            'part' => [
                [
                    'key' => 'categories',
                    'value' => [
                        'mechanical' => 'Mécanique',
                        'electrical' => 'Électrique',
                        'hydraulic' => 'Hydraulique',
                        'pneumatic' => 'Pneumatique',
                        'consumable' => 'Consommable',
                        'safety' => 'Sécurité',
                        'other' => 'Autre',
                    ],
                    'type' => 'json',
                    'label' => 'Catégories de pièces',
                    'is_system' => true,
                    'sort_order' => 1,
                ],
                [
                    'key' => 'low_stock_threshold',
                    'value' => 5,
                    'type' => 'integer',
                    'label' => 'Seuil de stock bas',
                    'description' => 'Alerte quand le stock descend en dessous de cette valeur',
                    'sort_order' => 2,
                ],
            ],

            'notification' => [
                [
                    'key' => 'email_enabled',
                    'value' => false,
                    'type' => 'boolean',
                    'label' => 'Activer les notifications email',
                    'sort_order' => 1,
                ],
                [
                    'key' => 'auto_refresh_interval',
                    'value' => 60,
                    'type' => 'integer',
                    'label' => 'Intervalle de rafraîchissement (secondes)',
                    'description' => 'Fréquence de vérification des nouvelles notifications',
                    'sort_order' => 2,
                ],
            ],

            default => [],
        };
    }
}
