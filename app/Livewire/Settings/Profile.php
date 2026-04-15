<?php

namespace App\Livewire\Settings;

use App\Concerns\ProfileValidationRules;
use App\Models\School;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use WireUi\Traits\WireUiActions;

#[Title('Profile settings')]
class Profile extends Component
{
    use ProfileValidationRules, WireUiActions;

    // ── Profile fields ─────────────────────────────────────────

    public string $name  = '';
    public string $email = '';

    // ── School picker state ────────────────────────────────────

    public string $schoolSearch     = '';
    public bool   $showSchoolCreate = false;
    public string $newEmis          = '';
    public string $newSchoolName    = '';
    public string $newProvince      = '';
    public string $newDistrict      = '';
    public string $newType          = '';

    // ── Lifecycle ──────────────────────────────────────────────

    public function mount(): void
    {
        $this->name  = Auth::user()->name;
        $this->email = Auth::user()->email;
    }

    // ── Profile ────────────────────────────────────────────────

    public function updateProfileInformation(): void
    {
        $user      = Auth::user();
        $validated = $this->validate($this->profileRules($user->id));

        $user->fill($validated);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->dispatch('profile-updated', name: $user->name);
    }

    public function resendVerificationNotification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));
            return;
        }

        $user->sendEmailVerificationNotification();
        Session::flash('status', 'verification-link-sent');
    }

    #[Computed]
    public function hasUnverifiedEmail(): bool
    {
        return Auth::user() instanceof MustVerifyEmail && ! Auth::user()->hasVerifiedEmail();
    }

    #[Computed]
    public function showDeleteUser(): bool
    {
        return ! Auth::user() instanceof MustVerifyEmail
            || (Auth::user() instanceof MustVerifyEmail && Auth::user()->hasVerifiedEmail());
    }

    // ── School ─────────────────────────────────────────────────

    #[Computed]
    public function currentSchool(): ?School
    {
        return Auth::user()->load('school')->school;
    }

    #[Computed]
    public function schoolSearchResults()
    {
        if (strlen($this->schoolSearch) < 2) {
            return collect();
        }

        return School::where('emis_number', 'like', "%{$this->schoolSearch}%")
            ->orWhere('name', 'like', "%{$this->schoolSearch}%")
            ->orderByRaw("CASE WHEN emis_number = ? THEN 0 ELSE 1 END", [strtoupper($this->schoolSearch)])
            ->limit(8)
            ->get();
    }

    public function assignSchool(int $schoolId): void
    {
        Auth::user()->update(['school_id' => $schoolId]);
        $this->schoolSearch = '';
        unset($this->currentSchool, $this->schoolSearchResults);

        $this->notification()->success(
            title: 'School linked!',
            description: 'Your account is now associated with ' . Auth::user()->fresh()->school->name . '.',
        );
    }

    public function createSchool(): void
    {
        $this->validate([
            'newEmis'       => ['required', 'string', 'max:20', 'unique:schools,emis_number'],
            'newSchoolName' => ['required', 'string', 'max:255'],
            'newProvince'   => ['required', 'string', 'in:' . implode(',', School::PROVINCES)],
            'newDistrict'   => ['required', 'string', 'max:100'],
            'newType'       => ['required', 'string', 'in:' . implode(',', array_keys(School::TYPES))],
        ], [
            'newEmis.unique' => 'A school with this EMIS number already exists. Search for it instead.',
        ]);

        $school = School::create([
            'emis_number' => strtoupper(trim($this->newEmis)),
            'name'        => $this->newSchoolName,
            'province'    => $this->newProvince,
            'district'    => $this->newDistrict,
            'type'        => $this->newType,
            'created_by'  => Auth::id(),
        ]);

        Auth::user()->update(['school_id' => $school->id]);

        $this->showSchoolCreate = false;
        $this->newEmis = $this->newSchoolName = $this->newProvince = $this->newDistrict = $this->newType = '';
        unset($this->currentSchool, $this->schoolSearchResults);

        $this->notification()->success(
            title: 'School added!',
            description: "{$school->name} has been added to the directory and linked to your account.",
        );
    }

    public function unlinkSchool(): void
    {
        Auth::user()->update(['school_id' => null]);
        unset($this->currentSchool);
    }
}
