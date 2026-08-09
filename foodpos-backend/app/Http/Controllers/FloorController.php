<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFloorRequest;
use App\Http\Requests\UpdateFloorRequest;
use App\Models\Branch;
use App\Models\Floor;
use App\Support\ListingPerPage;
use App\Models\Table;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FloorController extends Controller
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

        return (int) (current_branch_id() ?? $branches->first()?->id);
    }

    private function assertBranchInLayout(int $branchId): void
    {
        if (! $this->layoutBranches()->contains('id', $branchId)) {
            abort(403, 'You cannot manage floors for this branch.');
        }
    }

    private function assertFloorAccessible(Floor $floor): void
    {
        $this->assertBranchInLayout((int) $floor->branch_id);
    }

    /**
     * Display a listing of floors for the selected branch.
     */
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

        $query = Floor::withoutGlobalScope('branch')->with('branch')->withCount('tables');
        if ($branchId) {
            $this->assertBranchInLayout($branchId);
            $query->where('branch_id', $branchId);
        } else {
            $query->whereRaw('1 = 0');
        }

        $floors = $query->orderBy('sort_order')->orderBy('name')->paginate($perPage)->withQueryString();

        return view('floors.index', compact('floors', 'branches', 'branchId'));
    }

    public function create(Request $request)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403);
        }

        $branches = $this->layoutBranches();
        if ($branches->isEmpty()) {
            return redirect()->route('floors.index')->with('error', 'Add a branch before creating floors.');
        }

        $branchId = $this->resolveLayoutBranchId($request);
        if (! $branches->contains('id', $branchId)) {
            abort(403);
        }
        $this->assertBranchInLayout($branchId);

        return view('floors.create', compact('branches', 'branchId'));
    }

    public function store(StoreFloorRequest $request)
    {
        $this->assertBranchInLayout((int) $request->validated('branch_id'));

        $data = $request->validated();
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', true);
        Floor::create($data);

        return redirect()->route('floors.index', ['branch_id' => $request->validated('branch_id')])
            ->with('success', 'Floor created successfully.');
    }

    public function show(Floor $floor)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403);
        }
        $this->assertFloorAccessible($floor);
        $floor->load(['branch', 'tables' => fn ($q) => $q->orderBy('name')]);

        return view('floors.show', compact('floor'));
    }

    public function edit(Floor $floor)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403);
        }
        $this->assertFloorAccessible($floor);
        $branches = $this->layoutBranches();

        return view('floors.edit', compact('floor', 'branches'));
    }

    public function update(UpdateFloorRequest $request, Floor $floor)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403);
        }
        $this->assertFloorAccessible($floor);
        $this->assertBranchInLayout((int) $request->validated('branch_id'));

        $newBranchId = (int) $request->validated('branch_id');
        if ((int) $floor->branch_id !== $newBranchId) {
            $companyId = (int) Branch::withoutGlobalScope('tenant')->where('id', $newBranchId)->value('company_id');
            Table::withoutGlobalScope('branch')->where('floor_id', $floor->id)->update([
                'branch_id' => $newBranchId,
                'company_id' => $companyId,
            ]);
        }

        $data = $request->validated();
        $data['sort_order'] = $data['sort_order'] ?? 0;
        $data['is_active'] = $request->boolean('is_active', $floor->is_active);
        $floor->update($data);

        return redirect()->route('floors.index', ['branch_id' => $floor->branch_id])
            ->with('success', 'Floor updated successfully.');
    }

    public function destroy(Floor $floor)
    {
        $user = Auth::user();
        if (! $user->isSuperAdmin() && ! $user->isCompanyAdmin()) {
            abort(403);
        }
        $this->assertFloorAccessible($floor);
        $branchId = (int) $floor->branch_id;
        $floor->delete();

        return redirect()->route('floors.index', ['branch_id' => $branchId])
            ->with('success', 'Floor deleted. Tables on this floor were unlinked.');
    }
}
