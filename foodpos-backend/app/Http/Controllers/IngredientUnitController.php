<?php

namespace App\Http\Controllers;

use App\Http\Requests\ImportIngredientUnitsRequest;
use App\Http\Requests\StoreIngredientUnitRequest;
use App\Http\Requests\UpdateIngredientUnitRequest;
use App\Models\Ingredient;
use App\Models\IngredientUnit;
use App\Services\IngredientUnitImportService;
use App\Support\IngredientUnitExport;
use App\Support\IngredientUnitImportSampleExport;
use App\Support\ListingPerPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IngredientUnitController extends Controller
{
    public function __construct(private IngredientUnitImportService $unitImporter) {}

    public function index(Request $request)
    {
        $search = trim((string) $request->input('search', ''));
        $perPage = ListingPerPage::fromRequest($request);

        $query = IngredientUnit::with(['company'])
            ->select('ingredient_units.*')
            ->selectSub(
                Ingredient::query()
                    ->selectRaw('count(distinct id)')
                    ->where(function ($q) {
                        $q->whereColumn('ingredients.purchase_unit_id', 'ingredient_units.id')
                            ->orWhereColumn('ingredients.consumption_unit_id', 'ingredient_units.id');
                    }),
                'linked_ingredients_count'
            )
            ->orderBy('name');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        $units = $query->paginate($perPage)->withQueryString();

        return view('ingredient-units.index', compact('units', 'search', 'perPage'));
    }

    public function create()
    {
        $user = Auth::user();
        $suggestedCode = IngredientUnit::generateNextCode($user->company_id);

        return view('ingredient-units.create', compact('suggestedCode'));
    }

    public function store(StoreIngredientUnitRequest $request)
    {
        $user = Auth::user();

        $unit = IngredientUnit::create([
            'company_id' => $user->company_id,
            'name' => $request->name,
            'code' => IngredientUnit::resolveCode($user->company_id, $request->input('code')),
            'description' => $request->description,
        ]);

        return redirect()
            ->route('ingredient-units.index')
            ->with('success', "Ingredient unit '{$unit->name}' created successfully.");
    }

    public function show(IngredientUnit $ingredientUnit)
    {
        $ingredientUnit->load('company');

        $linkedIngredients = Ingredient::query()
            ->where(function ($q) use ($ingredientUnit) {
                $q->where('purchase_unit_id', $ingredientUnit->id)
                    ->orWhere('consumption_unit_id', $ingredientUnit->id);
            })
            ->orderBy('name')
            ->get();

        return view('ingredient-units.show', compact('ingredientUnit', 'linkedIngredients'));
    }

    public function edit(IngredientUnit $ingredientUnit)
    {
        return view('ingredient-units.edit', compact('ingredientUnit'));
    }

    public function update(UpdateIngredientUnitRequest $request, IngredientUnit $ingredientUnit)
    {
        $code = trim((string) $request->input('code', ''));
        if ($code === '') {
            $code = $ingredientUnit->code ?: IngredientUnit::generateNextCode($ingredientUnit->company_id);
        }

        $ingredientUnit->update([
            'name' => $request->name,
            'code' => $code,
            'description' => $request->description,
        ]);

        return redirect()
            ->route('ingredient-units.index')
            ->with('success', "Ingredient unit '{$ingredientUnit->name}' updated successfully.");
    }

    public function destroy(IngredientUnit $ingredientUnit)
    {
        if ($ingredientUnit->isUsedByIngredients()) {
            return redirect()
                ->route('ingredient-units.index')
                ->with('error', "Cannot delete '{$ingredientUnit->name}' because it is used by one or more ingredients.");
        }

        $name = $ingredientUnit->name;
        $ingredientUnit->delete();

        return redirect()
            ->route('ingredient-units.index')
            ->with('success', "Ingredient unit '{$name}' deleted successfully.");
    }

    public function import(): View
    {
        return view('ingredient-units.import', [
            'expectedHeaders' => IngredientUnitImportService::expectedHeaders(),
            'importResult' => session('importResult'),
        ]);
    }

    public function importSample(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new IngredientUnitImportSampleExport)->download($format);
    }

    public function export(Request $request): StreamedResponse
    {
        $format = $request->query('format', 'xlsx');

        return (new IngredientUnitExport)->download($format);
    }

    public function importStore(ImportIngredientUnitsRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $result = $this->unitImporter->import($request->file('file'), (int) $user->company_id);

        $message = sprintf(
            'Import finished: %d created, %d updated.',
            $result['created'],
            $result['updated'],
        );

        if ($result['skipped'] > 0) {
            $message .= sprintf(' %d row(s) skipped.', $result['skipped']);
        }

        return redirect()
            ->route('ingredient-units.import')
            ->with('importResult', $result)
            ->with($result['created'] + $result['updated'] > 0 ? 'success' : 'error', $message);
    }
}
