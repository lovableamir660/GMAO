<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Equipment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class EquipmentController extends Controller
{
    /**
     * Liste des équipements
     */
    public function index(Request $request): JsonResponse
    {
        $query = Equipment::query()
            ->where('site_id', $request->user()->current_site_id)
            ->with(['site', 'location']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('criticality')) {
            $query->where('criticality', $request->criticality);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $sortField     = $request->input('sort', 'name');
        $sortDirection = $request->input('direction', 'asc');
        $query->orderBy($sortField, $sortDirection);

        $equipments = $query->paginate($request->input('per_page', 20));

        return response()->json($equipments);
    }

    /**
     * Créer un équipement
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'                 => 'nullable|string|max:50',
            'name'                 => 'required|string|max:255',
            'type'                 => 'nullable|string|max:100',
            'category'             => 'nullable|string|max:100',
            'brand'                => 'nullable|string|max:100',
            'model'                => 'nullable|string|max:100',
            'serial_number'        => 'nullable|string|max:100',
            'year'                 => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'status'               => 'nullable|in:operational,degraded,stopped,maintenance,repair,out_of_service,standby',
            'location'             => 'nullable|string|max:255',
            'location_id'          => 'nullable|integer',
            'department'           => 'nullable|string|max:100',
            'criticality'          => 'nullable|in:low,medium,high,critical',
            'installation_date'    => 'nullable|date',
            'warranty_expiry_date' => 'nullable|date',
            'description'          => 'nullable|string',
            'acquisition_date'     => 'nullable|date',
            'acquisition_cost'     => 'nullable|numeric|min:0',
            'warranty_expiry'      => 'nullable|date',
            'hour_counter'         => 'nullable|integer|min:0',
            'specifications'       => 'nullable',
            'notes'                => 'nullable|string',
            'photo'                => 'nullable|string',
            'is_active'            => 'boolean',
        ]);

        if (isset($validated['specifications']) && is_string($validated['specifications'])) {
            $decoded = json_decode($validated['specifications'], true);
            $validated['specifications'] = $decoded ?: null;
        }
        if (empty($validated['location_id'])) {
            $validated['location_id'] = null;
        }

        $validated['site_id'] = $request->user()->current_site_id;

        if (empty($validated['code'])) {
            $validated['code'] = Equipment::generateCode(
                $validated['site_id'],
                $validated['type'] ?? 'EQP'
            );
        }

        $equipment = Equipment::create($validated);

        return response()->json([
            'message' => 'Équipement créé avec succès',
            'data'    => $equipment->load('location'),
        ], 201);
    }

    /**
     * Afficher un équipement
     */
    public function show(Equipment $equipment): JsonResponse
    {
        $this->authorize('view', $equipment);
        $equipment->load(['site', 'location', 'workOrders', 'interventionRequests']);
        return response()->json($equipment);
    }

    /**
     * Mettre à jour un équipement
     */
    public function update(Request $request, Equipment $equipment): JsonResponse
    {
        $this->authorize('update', $equipment);

        $validated = $request->validate([
            'code'                  => 'sometimes|string|max:50',
            'name'                  => 'sometimes|required|string|max:255',
            'type'                  => 'nullable|string|max:100',
            'category'              => 'nullable|string|max:100',
            'brand'                 => 'nullable|string|max:100',
            'model'                 => 'nullable|string|max:100',
            'serial_number'         => 'nullable|string|max:100',
            'year'                  => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'status'                => 'nullable|in:operational,degraded,stopped,maintenance,repair,out_of_service,standby',
            'location'              => 'nullable|string|max:255',
            'location_id'           => 'nullable|integer',
            'department'            => 'nullable|string|max:100',
            'criticality'           => 'nullable|in:low,medium,high,critical',
            'installation_date'     => 'nullable|date',
            'warranty_expiry_date'  => 'nullable|date',
            'description'           => 'nullable|string',
            'acquisition_date'      => 'nullable|date',
            'acquisition_cost'      => 'nullable|numeric|min:0',
            'warranty_expiry'       => 'nullable|date',
            'next_maintenance_date' => 'nullable|date',
            'hour_counter'          => 'nullable|integer|min:0',
            'specifications'        => 'nullable',
            'notes'                 => 'nullable|string',
            'photo'                 => 'nullable|string',
            'is_active'             => 'boolean',
        ]);

        if (isset($validated['specifications']) && is_string($validated['specifications'])) {
            $decoded = json_decode($validated['specifications'], true);
            $validated['specifications'] = $decoded ?: null;
        }
        if (array_key_exists('location_id', $validated) && empty($validated['location_id'])) {
            $validated['location_id'] = null;
        }

        $equipment->update($validated);

        return response()->json([
            'message' => 'Équipement mis à jour avec succès',
            'data'    => $equipment->fresh()->load('location'),
        ]);
    }

    /**
     * Supprimer un équipement
     */
    public function destroy(Equipment $equipment): JsonResponse
    {
        $this->authorize('delete', $equipment);
        $equipment->delete();
        return response()->json(['message' => 'Équipement supprimé avec succès']);
    }

    /**
     * Changer le statut
     */
    public function changeStatus(Request $request, Equipment $equipment): JsonResponse
    {
        $this->authorize('update', $equipment);

        $validated = $request->validate([
            'status' => 'required|in:operational,degraded,stopped,maintenance,repair,out_of_service,standby',
        ]);

        $equipment->changeStatus($validated['status']);

        return response()->json([
            'message' => 'Statut mis à jour avec succès',
            'data'    => $equipment->fresh(),
        ]);
    }

    /**
     * Mettre à jour le compteur horaire
     */
    public function updateHourCounter(Request $request, Equipment $equipment): JsonResponse
    {
        $this->authorize('update', $equipment);

        $validated = $request->validate([
            'hour_counter' => 'required|integer|min:' . $equipment->hour_counter,
        ]);

        $equipment->updateHourCounter($validated['hour_counter']);

        return response()->json([
            'message' => 'Compteur horaire mis à jour',
            'data'    => $equipment->fresh(),
        ]);
    }

    /**
     * Statistiques des équipements
     */
    public function stats(Request $request): JsonResponse
    {
        $siteId = $request->user()->current_site_id;

        $stats = [
            'total'               => Equipment::where('site_id', $siteId)->count(),
            'operational'         => Equipment::where('site_id', $siteId)->where('status', 'operational')->count(),
            'degraded'            => Equipment::where('site_id', $siteId)->where('status', 'degraded')->count(),
            'stopped'             => Equipment::where('site_id', $siteId)->where('status', 'stopped')->count(),
            'maintenance'         => Equipment::where('site_id', $siteId)->where('status', 'maintenance')->count(),
            'in_maintenance'      => Equipment::where('site_id', $siteId)->whereIn('status', ['maintenance', 'repair', 'degraded'])->count(),
            'out_of_service'      => Equipment::where('site_id', $siteId)->whereIn('status', ['out_of_service', 'stopped'])->count(),
            'needing_maintenance' => Equipment::where('site_id', $siteId)->needingMaintenance()->count(),
        ];

        $stats['by_type'] = Equipment::where('site_id', $siteId)
            ->whereNotNull('type')
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        $stats['by_status'] = Equipment::where('site_id', $siteId)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $stats['by_criticality'] = Equipment::where('site_id', $siteId)
            ->whereNotNull('criticality')
            ->selectRaw('criticality, COUNT(*) as count')
            ->groupBy('criticality')
            ->pluck('count', 'criticality');

        return response()->json($stats);
    }

    // =========================================================================
    //  IMPORT EXCEL / CSV
    // =========================================================================

    /**
     * Importer des équipements depuis un fichier Excel ou CSV.
     *
     * Colonnes reconnues (noms flexibles) :
     *   code, name/nom, type, category/categorie, brand/marque, model/modele,
     *   serial_number/numero_serie, year/annee, status/statut,
     *   criticality/criticite, department/departement,
     *   installation_date, acquisition_date, acquisition_cost/cout_acquisition,
     *   warranty_expiry_date/garantie, hour_counter/compteur_horaire,
     *   description, notes, is_active/actif
     *
     * Règle de mise à jour : si un équipement avec le même code (ou serial_number)
     * existe déjà sur le site, il est mis à jour ; sinon il est créé.
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt',
        ]);

        $file = $request->file('file');
        $siteId = $request->user()->current_site_id;

        if (!$file->isValid()) {
            return response()->json(['message' => 'Fichier invalide.'], 400);
        }

        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, ['xlsx', 'xls', 'csv'])) {
            return response()->json(['message' => 'Format non supporté. Utilisez xlsx, xls ou csv.'], 400);
        }

        // ── Lecture du fichier ────────────────────────────────────────────────
        try {
            if (in_array($extension, ['xlsx', 'xls'])) {
                $reader = $extension === 'xls'
                    ? IOFactory::createReader('Xls')
                    : new XlsxReader();
            } else {
                $reader = new CsvReader();
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                $reader->setSheetIndex(0);
            }

            $spreadsheet = $reader->load($file->getRealPath());
            $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Impossible de lire le fichier : ' . $e->getMessage()], 400);
        }

        // ── Traduction des valeurs de statut ──────────────────────────────────
        $statusMap = [
            'opérationnel' => 'operational', 'operationnel' => 'operational', 'operational' => 'operational',
            'dégradé'      => 'degraded',    'degrade'      => 'degraded',    'degraded'    => 'degraded',
            'arrêté'       => 'stopped',     'arrete'       => 'stopped',     'stopped'     => 'stopped',
            'en maintenance' => 'maintenance', 'maintenance' => 'maintenance',
            'en réparation'  => 'repair',     'reparation'  => 'repair',      'repair'      => 'repair',
            'hors service'   => 'out_of_service', 'out_of_service' => 'out_of_service',
            'en veille'      => 'standby',    'standby'     => 'standby',
        ];

        $criticalityMap = [
            'faible'   => 'low',      'low'      => 'low',
            'moyenne'  => 'medium',   'medium'   => 'medium',
            'haute'    => 'high',     'high'     => 'high',
            'critique' => 'critical', 'critical' => 'critical',
        ];

        // ── Parcours des lignes ───────────────────────────────────────────────
        $imported = 0;
        $updated  = 0;
        $skipped  = 0;
        $errors   = [];

        $header = null;

        foreach ($rows as $index => $row) {
            // Ignorer les lignes vides
            if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            // Première ligne non-vide = en-têtes
            if (!$header) {
                $header = array_map(fn($h) => strtolower(trim((string) $h)), $row);
                continue;
            }

            // Mapper colonnes → valeurs
            $rowData = [];
            foreach ($header as $colIndex => $colName) {
                $rowData[$colName] = isset($row[$colIndex]) ? trim((string) $row[$colIndex]) : '';
            }

            // Helper : récupérer la première colonne trouvée parmi plusieurs noms
            $get = fn(array $keys) => array_reduce(
                $keys,
                fn($carry, $key) => $carry ?? (isset($rowData[$key]) && $rowData[$key] !== '' ? $rowData[$key] : null),
                null
            );

            // Le nom est obligatoire
            $name = $get(['name', 'nom', 'equipment_name', 'equipement']);
            if (!$name) {
                $errors[] = "Ligne {$index} : nom manquant, ligne ignorée.";
                $skipped++;
                continue;
            }

            // ── Construire le tableau de données ─────────────────────────────
            $rawStatus      = strtolower((string) ($get(['status', 'statut']) ?? ''));
            $rawCriticality = strtolower((string) ($get(['criticality', 'criticite', 'criticité']) ?? ''));

            $eqData = [
                'name'          => $name,
                'type'          => $get(['type']),
                'category'      => $get(['category', 'categorie', 'catégorie']),
                'brand'         => $get(['brand', 'marque']),
                'model'         => $get(['model', 'modele', 'modèle']),
                'serial_number' => $get(['serial_number', 'numero_serie', 'numéro_série', 'sn']),
                'department'    => $get(['department', 'departement', 'département']),
                'description'   => $get(['description']),
                'notes'         => $get(['notes', 'remarques']),
                'status'        => $statusMap[$rawStatus] ?? 'operational',
                'criticality'   => $criticalityMap[$rawCriticality] ?? 'medium',
            ];

            // Code (optionnel)
            $code = $get(['code']);
            if ($code) {
                $eqData['code'] = $code;
            }

            // Année
            $year = $get(['year', 'annee', 'année']);
            if ($year && is_numeric($year)) {
                $eqData['year'] = (int) $year;
            }

            // Compteur horaire
            $counter = $get(['hour_counter', 'compteur_horaire', 'compteur', 'heures']);
            if ($counter !== null && is_numeric(str_replace([' ', ','], ['', '.'], $counter))) {
                $eqData['hour_counter'] = (int) str_replace([' ', ','], ['', '.'], $counter);
            }

            // Coût acquisition
            $cost = $get(['acquisition_cost', 'cout_acquisition', 'coût_acquisition', 'cout', 'coût']);
            if ($cost !== null) {
                $costClean = str_replace([' ', ','], ['', '.'], $cost);
                if (is_numeric($costClean)) {
                    $eqData['acquisition_cost'] = (float) $costClean;
                }
            }

            // Dates (formats : dd/mm/yyyy, yyyy-mm-dd, mm/dd/yyyy)
            foreach ([
                'installation_date'    => ['installation_date', 'date_installation', 'date installation'],
                'acquisition_date'     => ['acquisition_date',  'date_acquisition',  'date acquisition'],
                'warranty_expiry_date' => ['warranty_expiry_date', 'garantie', 'expiration_garantie', 'warranty'],
            ] as $field => $keys) {
                $raw = $get($keys);
                if ($raw) {
                    $parsed = $this->parseDate($raw);
                    if ($parsed) {
                        $eqData[$field] = $parsed;
                    }
                }
            }

            // Actif
            $isActive = $get(['is_active', 'actif', 'active']);
            if ($isActive !== null) {
                $eqData['is_active'] = in_array(strtolower($isActive), ['1', 'oui', 'yes', 'true', 'actif']);
            } else {
                $eqData['is_active'] = true;
            }

            $eqData['site_id'] = $siteId;

            // ── Créer ou mettre à jour ────────────────────────────────────────
            try {
                // Chercher par code ou serial_number sur ce site
                $existing = null;
                if (!empty($eqData['code'])) {
                    $existing = Equipment::where('site_id', $siteId)
                        ->where('code', $eqData['code'])
                        ->first();
                }
                if (!$existing && !empty($eqData['serial_number'])) {
                    $existing = Equipment::where('site_id', $siteId)
                        ->where('serial_number', $eqData['serial_number'])
                        ->first();
                }

                if ($existing) {
                    // Mise à jour : ne modifier que les champs non-vides du fichier
                    $updateData = array_filter($eqData, fn($v) => $v !== null && $v !== '');
                    unset($updateData['site_id']); // ne pas écraser le site
                    $existing->update($updateData);
                    $updated++;
                } else {
                    // Création : générer le code si absent
                    if (empty($eqData['code'])) {
                        $eqData['code'] = Equipment::generateCode($siteId, $eqData['type'] ?? 'EQP');
                    }
                    Equipment::create($eqData);
                    $imported++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Ligne {$index} ({$name}) : " . $e->getMessage();
                $skipped++;
            }
        }

        return response()->json([
            'message'  => 'Import terminé',
            'imported' => $imported,
            'updated'  => $updated,
            'skipped'  => $skipped,
            'errors'   => $errors,
        ]);
    }

    /**
     * Tenter de parser une date dans plusieurs formats courants.
     * Retourne une chaîne 'Y-m-d' ou null.
     */
    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if (!$raw) return null;

        // Formats testés dans l'ordre
        $formats = ['d/m/Y', 'Y-m-d', 'm/d/Y', 'd-m-Y', 'Y/m/d'];
        foreach ($formats as $fmt) {
            $d = \DateTime::createFromFormat($fmt, $raw);
            if ($d && $d->format($fmt) === $raw) {
                return $d->format('Y-m-d');
            }
        }

        // Tentative générique PHP
        try {
            $ts = strtotime($raw);
            if ($ts !== false) {
                return date('Y-m-d', $ts);
            }
        } catch (\Throwable $e) {}

        return null;
    }

    // =========================================================================
    //  EXPORT EXCEL
    // =========================================================================

    /**
     * Exporter les équipements en Excel (.xlsx)
     *
     * Supporte les mêmes filtres que index() :
     * ?search=&status=&type=&criticality=&category=&location_id=&is_active=
     */
    public function export(Request $request)
    {
        $query = Equipment::query()
            ->where('site_id', $request->user()->current_site_id)
            ->with(['location']);

        if ($request->filled('search')) {
            $query->search($request->search);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('criticality')) {
            $query->where('criticality', $request->criticality);
        }
        if ($request->filled('location_id')) {
            $query->where('location_id', $request->location_id);
        }
        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }
        if ($request->filled('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $query->orderBy('name');

        $statusLabels = [
            'operational'    => 'Opérationnel',
            'degraded'       => 'Dégradé',
            'stopped'        => 'Arrêté',
            'maintenance'    => 'En maintenance',
            'repair'         => 'En réparation',
            'out_of_service' => 'Hors service',
            'standby'        => 'En veille',
        ];

        $criticalityLabels = [
            'low'      => 'Faible',
            'medium'   => 'Moyenne',
            'high'     => 'Haute',
            'critical' => 'Critique',
        ];

        $filename = 'equipements_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($query, $statusLabels, $criticalityLabels) {

            $spreadsheet = new Spreadsheet();
            $sheet       = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Équipements');

            // ── En-têtes ──────────────────────────────────────────────────────
            $headers = [
                'A' => 'Code',
                'B' => 'Nom',
                'C' => 'Type',
                'D' => 'Catégorie',
                'E' => 'Marque',
                'F' => 'Modèle',
                'G' => 'N° Série',
                'H' => 'Année',
                'I' => 'Statut',
                'J' => 'Criticité',
                'K' => 'Emplacement',
                'L' => 'Département',
                'M' => 'Date installation',
                'N' => 'Date acquisition',
                'O' => 'Coût acquisition (DA)',
                'P' => 'Expiration garantie',
                'Q' => 'Compteur horaire (h)',
                'R' => 'Actif',
                'S' => 'Notes',
            ];

            foreach ($headers as $col => $label) {
                $sheet->setCellValue("{$col}1", $label);
            }

            $sheet->getStyle('A1:S1')->applyFromArray([
                'font' => [
                    'bold'  => true,
                    'color' => ['rgb' => 'FFFFFF'],
                    'size'  => 11,
                    'name'  => 'Arial',
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '2C3E50'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color'       => ['rgb' => '455A64'],
                    ],
                ],
            ]);
            $sheet->getRowDimension(1)->setRowHeight(22);

            // ── Données ───────────────────────────────────────────────────────
            $row = 2;

            $query->chunk(200, function ($equipments) use ($sheet, $statusLabels, $criticalityLabels, &$row) {
                foreach ($equipments as $eq) {
                    $sheet->setCellValue("A{$row}", $eq->code ?? '');
                    $sheet->setCellValue("B{$row}", $eq->name ?? '');
                    $sheet->setCellValue("C{$row}", $eq->type ?? '');
                    $sheet->setCellValue("D{$row}", $eq->category ?? '');
                    $sheet->setCellValue("E{$row}", $eq->brand ?? '');
                    $sheet->setCellValue("F{$row}", $eq->model ?? '');
                    $sheet->setCellValue("G{$row}", $eq->serial_number ?? '');
                    $sheet->setCellValue("H{$row}", $eq->year ?? '');
                    $sheet->setCellValue("I{$row}", $statusLabels[$eq->status] ?? $eq->status ?? '');
                    $sheet->setCellValue("J{$row}", $criticalityLabels[$eq->criticality] ?? $eq->criticality ?? '');
                    $sheet->setCellValue("K{$row}", $eq->location?->name ?? ($eq->location ?? ''));
                    $sheet->setCellValue("L{$row}", $eq->department ?? '');
                    $sheet->setCellValue("M{$row}", $eq->installation_date?->format('d/m/Y') ?? '');
                    $sheet->setCellValue("N{$row}", $eq->acquisition_date?->format('d/m/Y') ?? '');
                    $sheet->setCellValue("O{$row}", $eq->acquisition_cost ?? '');
                    $sheet->setCellValue("P{$row}", ($eq->warranty_expiry_date ?? $eq->warranty_expiry)?->format('d/m/Y') ?? '');
                    $sheet->setCellValue("Q{$row}", $eq->hour_counter ?? 0);
                    $sheet->setCellValue("R{$row}", $eq->is_active ? 'Oui' : 'Non');
                    $sheet->setCellValue("S{$row}", $eq->notes ?? '');

                    // Couleur de fond selon statut
                    $bgColor = match ($eq->status) {
                        'operational'              => 'F0FFF4',
                        'maintenance', 'repair'    => 'FFFBEB',
                        'stopped', 'out_of_service'=> 'FFF5F5',
                        'degraded'                 => 'FFF8F0',
                        default                    => 'FFFFFF',
                    };

                    $sheet->getStyle("A{$row}:S{$row}")->applyFromArray([
                        'fill' => [
                            'fillType'   => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $bgColor],
                        ],
                        'borders' => [
                            'allBorders' => [
                                'borderStyle' => Border::BORDER_THIN,
                                'color'       => ['rgb' => 'DDDDDD'],
                            ],
                        ],
                        'font' => ['name' => 'Arial', 'size' => 10],
                    ]);

                    // Couleur criticité (colonne J)
                    $critColor = match ($eq->criticality) {
                        'critical' => 'FECACA',
                        'high'     => 'FED7AA',
                        'medium'   => 'FEF08A',
                        'low'      => 'BBF7D0',
                        default    => null,
                    };
                    if ($critColor) {
                        $sheet->getStyle("J{$row}")->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB($critColor);
                    }

                    $row++;
                }
            });

            // ── Largeur des colonnes ──────────────────────────────────────────
            foreach ([
                'A' => 14, 'B' => 28, 'C' => 16, 'D' => 16,
                'E' => 14, 'F' => 14, 'G' => 16, 'H' =>  8,
                'I' => 16, 'J' => 12, 'K' => 20, 'L' => 16,
                'M' => 16, 'N' => 16, 'O' => 20, 'P' => 18,
                'Q' => 18, 'R' =>  8, 'S' => 30,
            ] as $col => $width) {
                $sheet->getColumnDimension($col)->setWidth($width);
            }

            $sheet->freezePane('A2');

            $lastRow = max($row - 1, 1);
            $sheet->setAutoFilter("A1:S{$lastRow}");

            $writer = new XlsxWriter($spreadsheet);
            $writer->save('php://output');

        }, $filename, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Cache-Control'       => 'max-age=0',
        ]);
    }
}