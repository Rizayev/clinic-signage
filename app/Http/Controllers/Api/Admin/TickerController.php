<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTickerRequest;
use App\Http\Requests\UpdateTickerRequest;
use App\Http\Resources\TickerResource;
use App\Models\Ticker;
use Illuminate\Http\Request;

class TickerController extends Controller
{
    public function index(Request $request)
    {
        $tickers = Ticker::query()
            ->when($request->filled('branch_id'), function ($query) use ($request) {
                $query->where('branch_id', $request->input('branch_id'));
            })
            ->latest()
            ->paginate(20);

        return TickerResource::collection($tickers);
    }

    public function store(StoreTickerRequest $request)
    {
        $data = $request->validated();
        // started_at drives the "duration N minutes" window — stamp it on activation.
        $data['started_at'] = $request->boolean('is_active', true) ? now() : null;

        $ticker = Ticker::create($data);

        return new TickerResource($ticker);
    }

    public function show(Ticker $ticker)
    {
        return new TickerResource($ticker);
    }

    public function update(UpdateTickerRequest $request, Ticker $ticker)
    {
        $data = $request->validated();

        // Reset the duration clock only when activity actually transitions.
        $wasActive = (bool) $ticker->is_active;
        $willBeActive = $request->boolean('is_active', $ticker->is_active);
        if (! $wasActive && $willBeActive) {
            $data['started_at'] = now();
        } elseif ($wasActive && ! $willBeActive) {
            $data['started_at'] = null;
        }

        $ticker->update($data);

        return new TickerResource($ticker);
    }

    public function destroy(Ticker $ticker)
    {
        $ticker->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
