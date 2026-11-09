<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use Illuminate\Http\Request;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $query = Doctor::with(['user', 'specialty'])->where('is_active', true);

        // Фильтр по специальности
        if ($request->has('specialty_id')) {
            $query->where('specialty_id', $request->specialty_id);
        }

        // Поиск по ФИО врача
        if ($request->has('q')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->q . '%');
            });
        }

        // Сортировка
        $sortField = $request->get('sort', 'id');
        $sortDirection = $request->get('direction', 'asc');
        
        if ($sortField === 'name') {
            $query->join('users', 'doctors.user_id', '=', 'users.id')
                  ->orderBy('users.name', $sortDirection)
                  ->select('doctors.*');
        } else {
            $query->orderBy($sortField, $sortDirection);
        }

        return response()->json($query->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id|unique:doctors,user_id',
            'specialty_id' => 'required|exists:specialties,id',
            'experience_years' => 'nullable|integer|min:0',
            'bio' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $doctor = Doctor::create($validated);

        return response()->json($doctor->load(['user', 'specialty']), 201);
    }

    public function show(Doctor $doctor)
    {
        return response()->json($doctor->load(['user', 'specialty']));
    }

    public function update(Request $request, Doctor $doctor)
    {
        $validated = $request->validate([
            'specialty_id' => 'sometimes|exists:specialties,id',
            'experience_years' => 'nullable|integer|min:0',
            'bio' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $doctor->update($validated);

        return response()->json($doctor->load(['user', 'specialty']));
    }

    public function destroy(Doctor $doctor)
    {
        $doctor->delete();
        return response()->json(['message' => 'Врач удалён (soft delete)']);
    }
}