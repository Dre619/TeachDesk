<?php

namespace App\Livewire\Concerns;

use App\Models\ClassRoom;
use Illuminate\Support\Facades\Auth;

trait HasClassRoomRole
{
    // These public properties are automatically available in the blade
    public string $myRole     = 'form_teacher';  // 'form_teacher' | 'subject_teacher'
    public array  $mySubjects = [];              // empty = sees all subjects (form teacher)

    /**
     * Call this at the end of mount() in any class-scoped Livewire component.
     *
     * Example:
     *
     *   public function mount(int $classId): void
     *   {
     *       $classroom = ClassRoom::accessibleByTeacher(Auth::id())->findOrFail($classId);
     *       $this->classId = $classId;
     *       $this->resolveRole($classroom);
     *   }
     */
    protected function resolveRole(ClassRoom $classroom): void
    {
        $userId = Auth::id();

        if ($classroom->isOwnedBy($userId)) {
            $this->myRole     = 'form_teacher';
            $this->mySubjects = [];
        } else {
            $this->myRole     = 'subject_teacher';
            $this->mySubjects = $classroom->memberSubjects($userId);
        }
    }

    public function isFormTeacher(): bool
    {
        return $this->myRole === 'form_teacher';
    }

    public function isSubjectTeacher(): bool
    {
        return $this->myRole === 'subject_teacher';
    }
}
