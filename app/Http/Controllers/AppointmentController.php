<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Appointment::with(['doctor.user', 'doctor.specialty', 'patient']);

        // Пациент видит только свои записи, админ — все
        if ($user->role === 'patient') {
            $query->where('patient_id', $user->id);
        } elseif ($user->role === 'doctor') {
            $doctor = Doctor::where('user_id', $user->id)->first();
            if ($doctor) {
                $query->where('doctor_id', $doctor->id);
            } else {
                return response()->json(['data' => []]);
            }
        }

        // Фильтры
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date')) {
            $query->whereDate('starts_at', $request->date);
        }

        return response()->json($query->orderBy('starts_at', 'asc')->paginate(15));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            'starts_at' => 'required|date|after:now',
            'ends_at' => 'required|date|after:starts_at',
            'comment' => 'nullable|string',
        ]);

        // Проверка на пересечение слотов
        $isBooked = Appointment::where('doctor_id', $validated['doctor_id'])
            ->where('status', '!=', 'cancelled')
            ->where(function ($q) use ($validated) {
                $q->whereBetween('starts_at', [$validated['starts_at'], $validated['ends_at']])
                  ->orWhereBetween('ends_at', [$validated['starts_at'], $validated['ends_at']])
                  ->orWhere(function ($q2) use ($validated) {
                      $q2->where('starts_at', '<=', $validated['starts_at'])
                         ->where('ends_at', '>=', $validated['ends_at']);
                  });
            })->exists();

        if ($isBooked) {
            throw ValidationException::withMessages([
                'starts_at' => ['Этот слот уже занят другим пациентом.'],
            ]);
        }

        $appointment = Appointment::create([
            'patient_id' => $request->user()->id,
            'doctor_id' => $validated['doctor_id'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
            'status' => 'pending',
            'comment' => $validated['comment'] ?? null,
        ]);

        return response()->json($appointment->load(['doctor.user', 'doctor.specialty']), 201);
    }

    public function destroy(Request $request, Appointment $appointment)
    {
        $user = $request->user();

        // Отменить может только сам пациент или админ
        if ($user->role === 'patient' && $appointment->patient_id !== $user->id) {
            return response()->json(['message' => 'Доступ запрещён.'], 403);
        }

        $appointment->update(['status' => 'cancelled']);
        $appointment->delete(); // Soft delete

        return response()->json(['message' => 'Запись отменена.']);
    }
}