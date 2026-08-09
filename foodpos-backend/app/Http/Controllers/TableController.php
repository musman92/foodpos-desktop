<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTableRequest;
use App\Http\Requests\UpdateTableRequest;
use App\Models\Branch;
use App\Models\Floor;
use App\Models\Table;
use App\Support\ListingPerPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TableController extends Controller
{
    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Branch>
     */
    private function layoutBranches()
    {
        $user = Auth::user();

        if ($user->isSuperAdmin()) {
            return Branch::with('company')->orderBy('company_id')->orderBy('name')->get();
        }

        $branchId = current_branch_id();
        if ($branchId) {
            $branch = Branch::where('id', $branchId)->where('status', 'active')->first();

            return $branch ? collect([$branch]) : collect();
        }

        return collect();
    }

    private function resolveLayoutBranchId(Request $request): int
    {
        $user = Auth::user();
        $branches = $this->layoutBranches();

        if ($user->isSuperAdmin()) {
            return (int) $request->query('branch_id', $branches->first()?->id);
        }

        $branchId = current_branch_id() ?? $branches->first()?->id;

        return (int) $branchId;
    }

    private function assertBranchInLayout(int $branchId): void
    {
        if (! $this->layoutBranches()->contains('id', $branchId)) {
            abort(403, 'You cannot manage tables for this branch.');
        }
    }

    private function assertTableAccessible(Table $table): void
    {
        $this->assertBranchInLayout((int) $table->branch_id);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Floor>
     */
    private function floorsForBranch(int $branchId)
    {
        return Floor::withoutGlobalScope('branch')
            ->where('branch_id', $branchId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public function index(Request $request)
    {
        $perPage = ListingPerPage::fromRequest($request);
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403);
        }

        $branches = $this->layoutBranches();
        $branchId = $this->resolveLayoutBranchId($request);

        if ($branchId && ! $branches->contains('id', $branchId)) {
            abort(403);
        }

        $query = Table::withoutGlobalScope('branch')->with(['branch', 'floor']);
        if ($branchId) {
            $this->assertBranchInLayout($branchId);
            $query->where('branch_id', $branchId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $tables = $query->orderBy('name')->paginate($perPage)->withQueryString();

        return view('tables.index', compact('tables', 'branches', 'branchId'));
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403);
        }

        $branches = $this->layoutBranches();
        $branchId = $this->resolveLayoutBranchId($request);
        if (! $branchId || ! $branches->contains('id', $branchId)) {
            abort(403, 'Select a branch to add tables.');
        }
        $this->assertBranchInLayout($branchId);
        $floors = $this->floorsForBranch($branchId);

        return view('tables.create', compact('branches', 'branchId', 'floors'));
    }

    public function store(StoreTableRequest $request)
    {
        $this->assertBranchInLayout((int) $request->validated('branch_id'));

        $v = $request->validated();
        $companyId = (int) Branch::withoutGlobalScope('tenant')->where('id', $v['branch_id'])->value('company_id');
        $slug = ($v['slug'] ?? null) !== null && ($v['slug'] ?? '') !== ''
            ? $v['slug']
            : Table::generateUniqueSlug($companyId, $v['name']);
        unset($v['slug']);

        Table::create(array_merge($v, [
            'company_id' => $companyId,
            'slug' => $slug,
            'capacity' => $v['capacity'] ?? 4,
        ]));

        return redirect()->route('tables.index', ['branch_id' => $request->validated('branch_id')])
            ->with('success', 'Table created successfully.');
    }

    public function show(Table $table)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403);
        }
        $this->assertTableAccessible($table);
        $table->load(['branch', 'floor']);

        return view('tables.show', compact('table'));
    }

    public function edit(Table $table)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403);
        }
        $this->assertTableAccessible($table);
        $branches = $this->layoutBranches();
        $floors = $this->floorsForBranch((int) $table->branch_id);

        return view('tables.edit', compact('table', 'branches', 'floors'));
    }

    public function update(UpdateTableRequest $request, Table $table)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403);
        }
        $this->assertTableAccessible($table);
        $this->assertBranchInLayout((int) $request->validated('branch_id'));

        $data = $request->validated();
        $data['company_id'] = (int) Branch::withoutGlobalScope('tenant')->where('id', $data['branch_id'])->value('company_id');
        if (array_key_exists('slug', $data) && ($data['slug'] === null || $data['slug'] === '')) {
            unset($data['slug']);
        }
        $data['capacity'] = $data['capacity'] ?? ($table->capacity ?? 4);
        $table->update($data);

        return redirect()->route('tables.index', ['branch_id' => $table->branch_id])
            ->with('success', 'Table updated successfully.');
    }

    public function destroy(Table $table)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403);
        }
        $this->assertTableAccessible($table);
        $branchId = (int) $table->branch_id;
        $table->delete();

        return redirect()->route('tables.index', ['branch_id' => $branchId])
            ->with('success', 'Table deleted.');
    }
}
