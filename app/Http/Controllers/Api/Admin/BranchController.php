<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $query = Branch::query()->withCount(['zones', 'devices']);

        if ($q = $request->query('q')) {
            $query->where('name', 'like', '%' . $q . '%');
        }

        return BranchResource::collection($query->paginate(20));
    }

    public function store(StoreBranchRequest $request)
    {
        $data = $request->validated();
        $data['timezone'] = $data['timezone'] ?? 'Asia/Baku';
        $data['status'] = $data['status'] ?? 'active';

        $branch = Branch::create($data);

        return new BranchResource($branch->loadCount(['zones', 'devices']));
    }

    public function show(Branch $branch)
    {
        return new BranchResource($branch->loadCount(['zones', 'devices']));
    }

    public function update(UpdateBranchRequest $request, Branch $branch)
    {
        $data = $request->validated();
        $data['timezone'] = $data['timezone'] ?? 'Asia/Baku';
        $data['status'] = $data['status'] ?? 'active';

        $branch->update($data);

        return new BranchResource($branch->loadCount(['zones', 'devices']));
    }

    public function destroy(Branch $branch)
    {
        $branch->delete();

        return response()->json(['success' => true]);
    }
}
