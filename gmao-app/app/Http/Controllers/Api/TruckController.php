<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Truck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;

class TruckController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Truck::where('site_id', $request->user()->current_site_id)
            ->with('currentDriver:id,code,first_name,last_name');

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('registration_number', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%")
                  ->orWhere('internal_code', 'like', "%{$request->search}%")
                  ->orWhere('brand', 'like', "%{$request->search}%")
                  ->orWhere('model', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->fuel_type) {
            $query->where('fuel_type', $request->fuel_type);
        }

        $trucks = $query->orderBy('code')->paginate($request->per_page ?? 15);

        return response()->json($trucks);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'internal_code'             => 'nullable|string|max:50',
            'registration_number'       => 'required|string|max:50',
            'brand'                     => 'nullable|string|max:100',
            'model'                     => 'nullable|string|max:100',
            'year'                      => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'type'                      => 'nullable|string|max:100',
            'capacity'                  => 'nullable|numeric|min:0',
            'capacity_unit'             => 'nullable|in:tonnes,m3,litres,palettes',
            'fuel_type'                 => 'nullable|string|max:50',
            'mileage'                   => 'nullable|integer|min:0',
            'status'                    => 'nullable|in:available,in_use,maintenance,out_of_service',
            'registration_date'         => 'nullable|date',
            'insurance_expiry_date'     => 'nullable|date',
            'technical_inspection_date' => 'nullable|date',
            'next_maintenance_date'     => 'nullable|date',
            'notes'                     => 'nullable|string',
            'current_driver_id'         => 'nullable|exists:drivers,id',
        ]);

        $siteId = $request->user()->current_site_id;
        $validated['site_id'] = $siteId;
        $validated['code'] = Truck::generateCode($siteId);
        $validated['created_by'] = $request->user()->id;

        if (!isset($validated['status'])) {
            $validated['status'] = 'available';
        }

        $truck = Truck::create($validated);

        return response()->json([
            'message' => 'Camion créé avec succès',
            'truck' => $truck->load('currentDriver:id,code,first_name,last_name'),
        ], 201);
    }

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
            return response()->json(['message' => 'Format de fichier non supporté.'], 400);
        }

        try {
            if (in_array($extension, ['xlsx', 'xls'])) {
                $reader = new XlsxReader();
                if ($extension === 'xls') {
                    $reader = IOFactory::createReader('Xls');
                }
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

        $imported = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        $header = null;
        foreach ($rows as $index => $row) {
            // Skip empty rows
            if (count(array_filter($row, fn($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            if (!$header) {
                $header = array_map(fn($h) => strtolower(trim((string) $h)), $row);
                continue;
            }

            $rowData = [];
            foreach ($header as $colIndex => $colName) {
                if (!isset($row[$colIndex])) {
                    continue;
                }
                $rowData[$colName] = trim((string) $row[$colIndex]);
            }

            $get = fn(array $keys) => array_reduce($keys, fn($carry, $key) => $carry ?? ($rowData[$key] ?? null), null);

            $registrationNumber = $get(['registration_number', 'immatriculation']);
            if (!$registrationNumber) {
                $errors[] = "Ligne {$index} : immatriculation manquante";
                $skipped++;
                continue;
            }

            $truckData = [
                'code'                      => $get(['code', 'system_code', 'truck_code']),
                'internal_code'             => $get(['internal_code', 'code_interne', 'code']),
                'registration_number'       => $registrationNumber,
                'brand'                     => $get(['brand', 'marque']),
                'model'                     => $get(['model', 'modele']),
                'year'                      => $get(['year', 'annee', 'année']) ? intval($get(['year', 'annee', 'année'])) : null,
                'type'                      => $get(['type', 'genre']),
                'capacity'                  => $get(['capacity', 'capacite', 'capacité']) ? floatval(str_replace(',', '.', $get(['capacity', 'capacite', 'capacité']))) : null,
                'capacity_unit'             => $get(['capacity_unit', 'unite', 'unité']),
                'fuel_type'                 => $get(['fuel_type', 'carburant']),
                'mileage'                   => $get(['mileage', 'kilometrage', 'kilométrage']) ? intval(str_replace([' ', ','], ['', '.'], $get(['mileage', 'kilometrage', 'kilométrage']))) : null,
                'status'                    => $get(['status', 'statut']),
                'registration_date'         => $get(['registration_date', 'date_immatriculation']),
                'insurance_expiry_date'     => $get(['insurance_expiry_date', 'date_assurance']),
                'technical_inspection_date' => $get(['technical_inspection_date', 'date_controle_technique', 'date_ct']),
                'next_maintenance_date'     => $get(['next_maintenance_date', 'date_prochaine_maintenance']),
                'notes'                     => $get(['notes', 'remarques']),
            ];

            // Force defaults
            if (empty($truckData['status'])) {
                $truckData['status'] = 'available';
            }

            $truckData['site_id'] = $siteId;
            $truckData['created_by'] = $request->user()->id;

            try {
                $truck = Truck::where('site_id', $siteId)
                    ->where('registration_number', $registrationNumber)
                    ->first();

                if ($truck) {
                    // Only update fields that have actual values (not null or empty)
                    $updateData = array_filter($truckData, fn($value) => $value !== null && $value !== '');
                    if (!empty($updateData)) {
                        $truck->update($updateData);
                    }
                    $updated++;
                } else {
                    // Ensure the required unique "code" is always set when creating a new truck
                    if (empty($truckData['code'])) {
                        $truckData['code'] = Truck::generateCode($siteId);
                    }

                    Truck::create($truckData);
                    $imported++;
                }
            } catch (\Throwable $e) {
                $errors[] = "Ligne {$index} : " . $e->getMessage();
                $skipped++;
                continue;
            }
        }

        return response()->json([
            'message' => 'Import terminé',
            'imported' => $imported,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    public function show(Truck $truck): JsonResponse
    {
        $truck->load(['currentDriver', 'createdBy:id,name']);

        return response()->json($truck);
    }

    public function update(Request $request, Truck $truck): JsonResponse
    {
        $validated = $request->validate([
            'internal_code'             => 'nullable|string|max:50',
            'registration_number'       => 'required|string|max:50',
            'brand'                     => 'nullable|string|max:100',
            'model'                     => 'nullable|string|max:100',
            'year'                      => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'type'                      => 'nullable|string|max:100',
            'capacity'                  => 'nullable|numeric|min:0',
            'capacity_unit'             => 'nullable|in:tonnes,m3,litres,palettes',
            'fuel_type'                 => 'nullable|string|max:50',
            'mileage'                   => 'nullable|integer|min:0',
            'status'                    => 'nullable|in:available,in_use,maintenance,out_of_service',
            'registration_date'         => 'nullable|date',
            'insurance_expiry_date'     => 'nullable|date',
            'technical_inspection_date' => 'nullable|date',
            'next_maintenance_date'     => 'nullable|date',
            'notes'                     => 'nullable|string',
            'current_driver_id'         => 'nullable|exists:drivers,id',
        ]);

        $truck->update($validated);

        return response()->json([
            'message' => 'Camion mis à jour',
            'truck' => $truck->load('currentDriver:id,code,first_name,last_name'),
        ]);
    }

    public function destroy(Truck $truck): JsonResponse
    {
        $truck->delete();

        return response()->json(['message' => 'Camion supprimé']);
    }

    public function export(Request $request)
    {
        $query = Truck::where('site_id', $request->user()->current_site_id);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('registration_number', 'like', "%{$request->search}%")
                  ->orWhere('code', 'like', "%{$request->search}%")
                  ->orWhere('internal_code', 'like', "%{$request->search}%")
                  ->orWhere('brand', 'like', "%{$request->search}%")
                  ->orWhere('model', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->type) {
            $query->where('type', $request->type);
        }

        if ($request->fuel_type) {
            $query->where('fuel_type', $request->fuel_type);
        }

        $columns = [
            'site_id',
            'code',
            'internal_code',
            'registration_number',
            'brand',
            'model',
            'year',
            'type',
            'capacity',
            'capacity_unit',
            'fuel_type',
            'mileage',
            'status',
            'current_driver_id',
            'registration_date',
            'insurance_expiry_date',
            'technical_inspection_date',
            'next_maintenance_date',
            'notes',
            'created_by',
            'created_at',
            'updated_at',
        ];

        $filename = 'camions_' . now()->format('Ymd_His') . '.xlsx';

        return response()->streamDownload(function () use ($query, $columns) {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Header
            foreach ($columns as $index => $col) {
                $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1) . '1';
                $sheet->setCellValue($cell, $col);
            }

            $rowIndex = 2;
            $query->orderBy('code')->chunk(200, function ($trucks) use ($sheet, $columns, &$rowIndex) {
                foreach ($trucks as $truck) {
                    foreach ($columns as $colIndex => $col) {
                        $cell = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1) . $rowIndex;
                        $sheet->setCellValue($cell, $truck->{$col});
                    }
                    $rowIndex++;
                }
            });

            $writer = new XlsxWriter($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function list(Request $request): JsonResponse
    {
        $trucks = Truck::where('site_id', $request->user()->current_site_id)
            ->whereIn('status', ['available', 'in_use'])
            ->orderBy('code')
            ->get(['id', 'code', 'internal_code', 'registration_number', 'brand', 'model', 'status']);

        return response()->json($trucks);
    }

    public function assignDriver(Request $request, Truck $truck): JsonResponse
    {
        $validated = $request->validate([
            'driver_id' => 'nullable|exists:drivers,id',
        ]);

        if ($validated['driver_id']) {
            Truck::where('current_driver_id', $validated['driver_id'])
                ->where('id', '!=', $truck->id)
                ->update(['current_driver_id' => null]);
        }

        $truck->update([
            'current_driver_id' => $validated['driver_id'],
            'status' => $validated['driver_id'] ? 'in_use' : 'available',
        ]);

        $truck->load('currentDriver');

        return response()->json([
            'message' => $validated['driver_id'] ? 'Chauffeur assigné' : 'Chauffeur retiré',
            'truck' => $truck,
        ]);
    }

    public function updateMileage(Request $request, Truck $truck): JsonResponse
    {
        $validated = $request->validate([
            'mileage' => 'required|integer|min:' . ($truck->mileage ?? 0),
        ]);

        $truck->update(['mileage' => $validated['mileage']]);

        return response()->json([
            'message' => 'Kilométrage mis à jour',
            'truck' => $truck,
        ]);
    }

    public function alerts(Request $request): JsonResponse
    {
        $trucks = Truck::where('site_id', $request->user()->current_site_id)
            ->where(function ($q) {
                $q->where('insurance_expiry_date', '<=', now()->addDays(30))
                  ->orWhere('technical_inspection_date', '<=', now()->addDays(30))
                  ->orWhere('next_maintenance_date', '<=', now());
            })
            ->with('currentDriver:id,first_name,last_name')
            ->get();

        return response()->json($trucks);
    }

    public function types(Request $request): JsonResponse
    {
        $types = Truck::where('site_id', $request->user()->current_site_id)
            ->whereNotNull('type')
            ->distinct()
            ->pluck('type');

        return response()->json($types);
    }
}
