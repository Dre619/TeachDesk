<?php

namespace App\Livewire;

use App\Models\ClassRoom;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SidebarClasses extends Component
{
    public bool $showOlderYears = false;

    #[Computed]
    public function classes()
    {
        $userId = Auth::id();

        $query = ClassRoom::forTeacher($userId)
            ->orderBy('academic_year', 'desc')
            ->orderBy('name');

        if (! $this->showOlderYears) {
            $query->forYear(now()->year);
        }

        return $query->get();
    }

    #[Computed]
    public function hasOlderClasses(): bool
    {
        return ClassRoom::forTeacher(Auth::id())
            ->where('academic_year', '<', now()->year)
            ->exists();
    }

    public function render()
    {
        return view('livewire.sidebar-classes', [
            'classes'        => $this->classes,
            'hasOlderClasses' => $this->hasOlderClasses,
            'currentClassId' => request()->route('classId'),
            'userId'         => Auth::id(),
        ]);
    }
}
