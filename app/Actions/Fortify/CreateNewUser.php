<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function create(array $input): User
    {
        // Base validation
        $rules = [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ];

        // Validate new school fields only when the teacher is creating one
        if (! empty($input['new_emis_number'])) {
            $rules = array_merge($rules, [
                'new_emis_number'    => ['required', 'string', 'max:20', 'unique:schools,emis_number'],
                'new_school_name'    => ['required', 'string', 'max:255'],
                'new_school_province'=> ['required', 'string', 'in:' . implode(',', School::PROVINCES)],
                'new_school_district'=> ['required', 'string', 'max:100'],
                'new_school_type'    => ['required', 'string', 'in:' . implode(',', array_keys(School::TYPES))],
            ]);
        }

        Validator::make($input, $rules, [
            'new_emis_number.unique' => 'A school with this EMIS number is already registered. Please search for it instead.',
        ])->validate();

        return DB::transaction(function () use ($input) {
            $schoolId = null;

            // Existing school selected
            if (! empty($input['school_id'])) {
                $schoolId = (int) $input['school_id'];
            }

            $user = User::create([
                'name'      => $input['name'],
                'email'     => $input['email'],
                'password'  => $input['password'],
                'school_id' => $schoolId, // may be null if skipped
            ]);

            // New school submitted — create it now that we have the user id
            if (! empty($input['new_emis_number'])) {
                $school = School::create([
                    'emis_number' => strtoupper(trim($input['new_emis_number'])),
                    'name'        => $input['new_school_name'],
                    'province'    => $input['new_school_province'],
                    'district'    => $input['new_school_district'],
                    'type'        => $input['new_school_type'],
                    'created_by'  => $user->id,
                ]);

                $user->update(['school_id' => $school->id]);
            }

            return $user;
        });
    }
}
