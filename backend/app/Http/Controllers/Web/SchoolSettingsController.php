<?php

namespace App\Http\Controllers\Web;

use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class SchoolSettingsController extends \App\Http\Controllers\Controller
{
    public function edit(TenantContext $tenant)
    {
        $this->authorize('school.manage');

        return view('school-admin.settings.school', [
            'school' => $tenant->assertBound(),
            'timezones' => \DateTimeZone::listIdentifiers(\DateTimeZone::AFRICA),
        ]);
    }

    public function update(Request $request, TenantContext $tenant)
    {
        $this->authorize('school.manage');
        $school = $tenant->assertBound();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'registration_number' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
            'motto' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],
            'timezone' => ['required', Rule::in(\DateTimeZone::listIdentifiers())],
            'message_locale' => ['sometimes', Rule::in(array_keys(config('app.supported_locales')))],
        ]);

        // Which language messages to parents are written in unless the parent chose one.
        if (isset($data['message_locale'])) {
            $data['settings'] = [...($school->settings ?? []), 'message_locale' => $data['message_locale']];
        }
        unset($data['message_locale']);

        // The URL slug and status are platform-controlled — a school admin
        // can change how the school is presented, not how it's addressed.
        $school->update($data);

        return redirect()->route('school-admin.settings.school')->with('status', __('School details updated.'));
    }

    public function uploadLogo(Request $request, TenantContext $tenant)
    {
        $this->authorize('school.manage');
        $school = $tenant->assertBound();

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        $path = $request->file('logo')->store("logos/{$school->id}", 'public');

        if ($school->logo_path && $school->logo_path !== $path) {
            Storage::disk('public')->delete($school->logo_path);
        }

        $school->update(['logo_path' => $path]);

        return redirect()->route('school-admin.settings.school')->with('status', __('Logo updated.'));
    }

    public function removeLogo(TenantContext $tenant)
    {
        $this->authorize('school.manage');
        $school = $tenant->assertBound();

        if ($school->logo_path) {
            Storage::disk('public')->delete($school->logo_path);
            $school->update(['logo_path' => null]);
        }

        return redirect()->route('school-admin.settings.school')->with('status', __('Logo removed.'));
    }

    /** Served through the app (not a public symlink) so it works on shared hosting. */
    public function logo(TenantContext $tenant)
    {
        $school = $tenant->get();

        abort_unless($school?->logo_path && Storage::disk('public')->exists($school->logo_path), 404);

        return response()->file(Storage::disk('public')->path($school->logo_path), ['Cache-Control' => 'private, max-age=3600']);
    }
}
