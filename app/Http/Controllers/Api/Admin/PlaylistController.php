<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePlaylistItemRequest;
use App\Http\Requests\StorePlaylistRequest;
use App\Http\Requests\UpdatePlaylistRequest;
use App\Http\Resources\PlaylistResource;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlaylistController extends Controller
{
    public function index(Request $request)
    {
        $query = Playlist::query()->withCount('items');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('description', 'like', "%{$q}%");
            });
        }

        return PlaylistResource::collection($query->latest()->paginate(20));
    }

    public function show(Playlist $playlist)
    {
        $playlist->load(['items' => function ($query) {
            $query->orderBy('sort_order')->with('media');
        }, 'assignments']);

        return PlaylistResource::make($playlist);
    }

    public function store(StorePlaylistRequest $request)
    {
        $data = $request->validated();
        $data['created_by'] = $request->user()?->id;
        $data['version'] = 1;

        $playlist = Playlist::create($data);

        return PlaylistResource::make($playlist)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdatePlaylistRequest $request, Playlist $playlist)
    {
        $playlist->fill($request->validated());
        $playlist->increment('version');
        $playlist->save();

        return PlaylistResource::make($playlist);
    }

    public function destroy(Playlist $playlist)
    {
        $playlist->delete();

        return response()->json(['message' => 'Deleted'], 200);
    }

    public function storeItem(StorePlaylistItemRequest $request, Playlist $playlist)
    {
        $data = $request->validated();
        $data['playlist_id'] = $playlist->id;
        $data['sort_order'] = (int) $playlist->items()->max('sort_order') + 1;

        if (! array_key_exists('transition_effect', $data) || $data['transition_effect'] === null) {
            $data['transition_effect'] = 'none';
        }

        PlaylistItem::create($data);

        $playlist->increment('version');

        return PlaylistResource::make($this->loadFull($playlist));
    }

    public function updateItem(Request $request, Playlist $playlist, PlaylistItem $item)
    {
        $data = $request->validate([
            'duration_seconds' => ['nullable', 'integer', 'min:1'],
            'transition_effect' => ['nullable', Rule::in(['none', 'fade', 'slide_left', 'slide_right', 'zoom', 'crossfade'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'days_of_week' => ['nullable', 'array'],
            'days_of_week.*' => ['integer', 'between:1,7'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $item->update($data);

        $playlist->increment('version');

        return PlaylistResource::make($this->loadFull($playlist));
    }

    public function destroyItem(Playlist $playlist, PlaylistItem $item)
    {
        $item->delete();

        $playlist->increment('version');

        return PlaylistResource::make($this->loadFull($playlist));
    }

    public function reorder(Request $request, Playlist $playlist)
    {
        $data = $request->validate([
            'order' => ['required', 'array'],
            'order.*' => ['integer'],
        ]);

        foreach ($data['order'] as $index => $itemId) {
            $playlist->items()->whereKey($itemId)->update(['sort_order' => $index]);
        }

        $playlist->increment('version');

        return PlaylistResource::make($this->loadFull($playlist));
    }

    public function assign(Request $request, Playlist $playlist)
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::in(['device', 'zone', 'branch', 'all'])],
            'target_id' => ['nullable', 'integer'],
            'priority' => ['nullable', 'integer'],
        ]);

        if ($data['target_type'] === 'all') {
            $data['target_id'] = null;
        }

        $playlist->assignments()->updateOrCreate(
            [
                'target_type' => $data['target_type'],
                'target_id' => $data['target_id'] ?? null,
            ],
            [
                'priority' => $data['priority'] ?? 0,
                'is_active' => true,
            ]
        );

        $playlist->load('assignments');

        return PlaylistResource::make($playlist);
    }

    protected function loadFull(Playlist $playlist): Playlist
    {
        return $playlist->fresh([
            'items' => function ($query) {
                $query->orderBy('sort_order')->with('media');
            },
            'assignments',
        ]);
    }
}
