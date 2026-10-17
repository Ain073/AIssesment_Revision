<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserAccountAvailability
{
    public function check(Request $request, ?User $user = null): JsonResponse
    {
        $validated = $request->validate([
            'field' => ['required', 'string', Rule::in(['email', 'employee_number', 'student_number'])],
            'value' => ['required', 'string', 'max:255'],
        ]);
        $field = $validated['field'];
        $rules = ['required', 'string', 'max:255'];
        if ($field === 'email') {
            $rules[] = 'email';
        }
        Validator::make([$field => $validated['value']], [$field => $rules])->validate();

        $unique = match ($field) {
            'email' => Rule::unique('users', 'email'),
            'employee_number' => Rule::unique('instructor_profiles', 'employee_number'),
            'student_number' => Rule::unique('student_profiles', 'student_number'),
        };

        // Exclude only the account already authorized by the controller, never a submitted ID.
        if ($user) {
            if ($field === 'email') {
                $unique->ignore($user->getKey());
            } elseif ($field === 'employee_number' && $user->instructorProfile) {
                $unique->ignore($user->instructorProfile->getKey(), 'instructor_profile_id');
            } elseif ($field === 'student_number' && $user->studentProfile) {
                $unique->ignore($user->studentProfile->getKey(), 'student_profile_id');
            }
        }

        $validator = Validator::make([$field => $validated['value']], [$field => [$unique]]);
        $available = $validator->passes();

        return response()->json([
            'field' => $field,
            'available' => $available,
            'message' => $available ? 'No duplicate found.' : $validator->errors()->first($field),
        ])->header('Cache-Control', 'no-store');
    }
}
