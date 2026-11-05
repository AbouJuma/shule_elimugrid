<?php

namespace App\Http\Controllers\Web;

use App\Models\Guardian;
use App\Support\Pagination\PageSize;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * The school-wide "Guardians" list — every guardian on file, independent
 * of which student page you got there from. Editing here touches only
 * the guardian's own details (name/phone/email/…); the per-student
 * relationship/is_primary fields stay on the student's own "Guardians"
 * tab (see StudentGuardianController), since those are properties of one
 * student-guardian link, not of the guardian themselves.
 */
class GuardianController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('guardians.manage');

        $guardians = Guardian::query()
            ->with(['students' => fn ($q) => $q->select('students.id', 'first_name', 'last_name', 'school_id'), 'user'])
            ->when($request->string('search')->isNotEmpty(), function ($q) use ($request) {
                $search = $request->string('search');
                $q->where(fn ($q) => $q->where('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")->orWhere('email', 'like', "%{$search}%"));
            })
            ->orderBy('name')
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.guardians.index', ['guardians' => $guardians]);
    }

    public function update(Request $request, Guardian $guardian)
    {
        $this->authorize('guardians.manage');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'locale' => ['nullable', Rule::in(array_keys(config('app.supported_locales')))],
        ]);

        $guardian->update([
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'occupation' => $data['occupation'] ?? null,
            'address' => $data['address'] ?? null,
            'locale' => $data['locale'] ?? null,
        ]);

        return redirect()->route('school-admin.guardians.index')
            ->with('status', __(":p1's details were updated.", ['p1' => $guardian->name]));
    }

    /**
     * Soft-deletes the guardian record. Any login they had is suspended
     * (not left dangling, able to sign in as a guardian the school just
     * removed) and they're detached from every student first, so the
     * roster's own "Guardians" tab doesn't keep showing a deleted guardian
     * through a stale pivot row.
     */
    public function destroy(Guardian $guardian)
    {
        $this->authorize('guardians.manage');

        if ($guardian->user_id) {
            $guardian->user->update(['status' => 'suspended']);
            $guardian->user->tokens()->delete();
        }

        $guardian->students()->detach();
        $guardian->delete();

        return redirect()->route('school-admin.guardians.index')
            ->with('status', __(":p1 was deleted.", ['p1' => $guardian->name]));
    }
}
