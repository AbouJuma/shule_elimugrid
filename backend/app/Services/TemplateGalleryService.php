<?php

namespace App\Services;

use App\Models\CertificateTemplate;
use App\Models\IdCardTemplate;
use App\Models\School;
use Illuminate\Support\Facades\DB;

/**
 * The pre-built design gallery (config/template_layouts.php) turned into
 * real, editable rows the first time a school looks at its templates — one
 * per catalog layout, "Classic" marked active so nothing changes for a
 * school that never touches this. A school can then recolour, rename,
 * delete, or add further templates of its own.
 */
class TemplateGalleryService
{
    public function ensureIdCardDefaults(School $school): void
    {
        if (IdCardTemplate::query()->exists()) {
            return;
        }

        foreach (config('template_layouts.id_card') as $key => $layout) {
            IdCardTemplate::create([
                'school_id' => $school->id,
                'name' => $layout['name'],
                'layout' => $key,
                'primary_color' => $layout['colors'][0],
                'secondary_color' => $layout['colors'][1],
                'is_default' => $key === 'classic',
            ]);
        }
    }

    public function ensureCertificateDefaults(School $school): void
    {
        if (CertificateTemplate::query()->exists()) {
            return;
        }

        foreach (config('template_layouts.certificate') as $key => $layout) {
            CertificateTemplate::create([
                'school_id' => $school->id,
                'name' => $layout['name'],
                'layout' => $key,
                'primary_color' => $layout['colors'][0],
                'secondary_color' => $layout['colors'][1],
                'is_default' => $key === 'classic',
            ]);
        }
    }

    /** Makes $template the only active one of its kind for the school. */
    public function activate(IdCardTemplate|CertificateTemplate $template): void
    {
        DB::transaction(function () use ($template) {
            $template->newQuery()->where('id', '!=', $template->id)->update(['is_default' => false]);
            $template->update(['is_default' => true]);
        });
    }
}
