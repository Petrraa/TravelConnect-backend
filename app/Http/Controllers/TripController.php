<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Models\TripImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TripController extends Controller
{
    // GET /api/trips
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $trips = Trip::query()
            ->with('user:id,username')
            ->where('user_id', $userId)
            ->orWhere('is_public', true)
            ->orderByDesc('id')
            ->get();

        return response()->json(['trips' => $trips]);
    }

    // POST /api/trips
    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'destination' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'travel_style' => ['nullable', 'string', 'max:50'],
            'pace' => ['nullable', 'string', 'max:50'],
            'is_public' => ['nullable', 'boolean'],

            // ✅ COVER SLIKA
            'image' => ['nullable', 'image', 'max:2048'],

            // ✅ GALERIJA
            'images' => ['nullable', 'array'],
            'images.*' => ['image', 'max:2048'],
        ]);

        // ✅ upload cover slike
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('trips', 'public');
        }

        $trip = Trip::create([
            ...$data,
            'user_id' => $request->user()->id,
            'is_public' => $data['is_public'] ?? false,
        ]);

        // ✅ upload galerije
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $path = $img->store('trips', 'public');

                TripImage::create([
                    'trip_id' => $trip->id,
                    'image' => $path,
                ]);
            }
        }

        return response()->json(['trip' => $trip], 201);
    }

    // GET /api/trips/{trip}
    public function show(Trip $trip)
    {
        $trip->load([
            'user:id,username',
            'days.items',
            'images', // ✅ GALERIJA
        ]);

        return response()->json(['trip' => $trip]);
    }

    // PUT /api/trips/{trip}
    public function update(Request $request, Trip $trip)
    {
        $this->authorizeOwner($request, $trip);

        $data = $request->validate([
            'title' => ['sometimes','required','string','max:255'],
            'destination' => ['sometimes','required','string','max:255'],
            'start_date' => ['nullable','date'],
            'end_date' => ['nullable','date'],
            'budget' => ['nullable','numeric','min:0'],
            'travel_style' => ['nullable','string','max:50'],
            'pace' => ['nullable','string','max:50'],
            'is_public' => ['nullable','boolean'],
            'image' => 'nullable|image|max:2048',

        ]);
        if ($request->hasFile('image')) {
            if ($trip->image) {
                Storage::disk('public')->delete($trip->image);
            }

            $data['image'] = $request->file('image')->store('trips', 'public');
        }

        $trip->update($data);

        // ✅ dodavanje novih slika u galeriju (ne brišemo stare)
        if ($request->hasFile('images')) {
            foreach ($request->file('images') as $img) {
                $path = $img->store('trips', 'public');

                TripImage::create([
                    'trip_id' => $trip->id,
                    'image' => $path,
                ]);
            }
        }

        return response()->json(['trip' => $trip]);
    }

    // DELETE /api/trips/{trip}
    public function destroy(Request $request, Trip $trip)
    {
        $this->authorizeOwner($request, $trip);

        // ✅ brišemo cover sliku
        if ($trip->image) {
            Storage::disk('public')->delete($trip->image);
        }

        // ✅ brišemo galeriju slika
        foreach ($trip->images as $img) {
            Storage::disk('public')->delete($img->image);
        }

        $trip->delete();

        return response()->json(['message' => 'Trip obrisan.']);
    }

    private function authorizeOwner(Request $request, Trip $trip): void
    {
        if ($trip->user_id !== $request->user()->id) {
            abort(403, 'Nemaš pravo uređivati ovo putovanje.');
        }
    }
}
