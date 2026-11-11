<?php

namespace App\Http\Controllers;

use App\Models\Specialty;
use Illuminate\Http\Request;

class SpecialtyController extends Controller
{
    public function index()
    {
        return response()->json(Specialty::paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:specialties,name',
            'description' => 'nullable|string',
        ]);

        $specialty = Specialty::create($validated);

        return response()->json($specialty, 201);
    }

    public function show(Specialty $specialty)
    {
        return response()->json($specialty);
    }

    public function update(Request $request, Specialty $specialty)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255|unique:specialties,name,' . $specialty->id,
            'description' => 'nullable|string',
        ]);

        $specialty->update($validated);

        return response()->json($specialty);
    }

    public function destroy(Specialty $specialty)
    {
        // Проверка, что к специальности не привязаны врачи
        if ($specialty->doctors()->exists()) {
            return response()->json(['message' => 'Нельзя удалить специальность, к которой привязаны врачи.'], 409);
        }

        $specialty->delete();

        return response()->json(null, 204);
    }
}