<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SchoolController extends Controller
{
    /**
     * Search for a school by EMIS number or partial name.
     * Public endpoint — used during registration before auth exists.
     */
    public function search(Request $request): JsonResponse
    {
        $query = trim($request->input('q', ''));

        if (\strlen($query) < 2) {
            return response()->json([]);
        }

        $schools = School::where('emis_number', 'like', "%{$query}%")
            ->orWhere('name', 'like', "%{$query}%")
            ->orderByRaw("CASE WHEN emis_number = ? THEN 0 ELSE 1 END", [strtoupper($query)])
            ->limit(10)
            ->get(['id', 'emis_number', 'name', 'province', 'district', 'type']);

        return response()->json($schools);
    }

    /**
     * Fetch a single school by exact EMIS number.
     */
    public function findByEmis(string $emis): JsonResponse
    {
        $school = School::byEmis($emis)->first(['id', 'emis_number', 'name', 'province', 'district', 'type']);

        if (! $school) {
            return response()->json(['found' => false]);
        }

        return response()->json(['found' => true, 'school' => $school]);
    }

    /**
     * Assign an existing school to the authenticated teacher (dashboard prompt).
     */
    public function assignToTeacher(int $schoolId): \Illuminate\Http\RedirectResponse
    {
        $school = School::findOrFail($schoolId);

        Auth::user()->update(['school_id' => $school->id]);

        return redirect()->route('dashboard')
            ->with('success', "Your account is now linked to {$school->name}.");
    }

    /**
     * Create a new school and assign it to the authenticated teacher (dashboard prompt).
     */
    public function createAndAssign(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'emis_number' => ['required', 'string', 'max:20', 'unique:schools,emis_number'],
            'name'        => ['required', 'string', 'max:255'],
            'province'    => ['required', 'string', 'in:' . implode(',', School::PROVINCES)],
            'district'    => ['required', 'string', 'max:100'],
            'type'        => ['required', 'string', 'in:' . implode(',', array_keys(School::TYPES))],
        ], [
            'emis_number.unique' => 'A school with this EMIS number already exists. Please search for it instead.',
        ]);

        $school = School::create([
            ...$data,
            'emis_number' => strtoupper(trim($data['emis_number'])),
            'created_by'  => Auth::id(),
        ]);

        Auth::user()->update(['school_id' => $school->id]);

        return redirect()->route('dashboard')
            ->with('success', "{$school->name} has been added and linked to your account.");
    }
}
