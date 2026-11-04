<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\IdCards\IssueIdCardRequest;
use App\Http\Requests\IdCards\RevokeIdCardRequest;
use App\Http\Requests\IdCards\StoreIdCardTemplateRequest;
use App\Http\Requests\IdCards\UpdateIdCardTemplateRequest;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\Student;
use App\Services\IdCardService;
use App\Services\TemplateGalleryService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class IdCardController extends \App\Http\Controllers\Controller
{
    public function index(Request $request, TenantContext $tenant, TemplateGalleryService $gallery)
    {
        $this->authorize('id_cards.view');

        $gallery->ensureIdCardDefaults($tenant->assertBound());

        return view('school-admin.id-cards.index', [
            'idCards' => IdCard::query()
                ->with(['student', 'template'])
                ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
                ->latest()
                ->paginate(PageSize::resolve($request))
                ->withQueryString(),
            'templates' => IdCardTemplate::query()->orderBy('is_default', 'desc')->orderBy('name')->get(),
            'layouts' => config('template_layouts.id_card'),
            'students' => Student::query()->where('status', 'active')->orderBy('first_name')->get(),
        ]);
    }

    public function store(IssueIdCardRequest $request, TenantContext $tenant, IdCardService $idCards)
    {
        $data = $request->validated();
        $student = Student::findOrFail($data['student_id']);

        $idCard = $idCards->issue($tenant->assertBound(), $student, $data, $request->user());

        return redirect()
            ->route('school-admin.id-cards.index')
            ->with('status', __('ID card :p1 issued to :p2.', ['p1' => $idCard->card_number, 'p2' => $student->fullName()]));
    }

    public function revoke(RevokeIdCardRequest $request, IdCard $idCard, IdCardService $idCards)
    {
        $idCards->revoke($idCard, $request->validated()['reason'] ?? null);

        return redirect()->route('school-admin.id-cards.index')->with('status', 'ID card revoked.');
    }

    public function storeTemplate(StoreIdCardTemplateRequest $request, TenantContext $tenant, TemplateGalleryService $gallery)
    {
        $template = IdCardTemplate::create(['school_id' => $tenant->id(), ...$request->validated()]);

        if ($template->is_default) {
            $gallery->activate($template);
        }

        return redirect()->route('school-admin.id-cards.index')->with('status', __('ID card template created.'));
    }

    public function updateTemplate(UpdateIdCardTemplateRequest $request, IdCardTemplate $idCardTemplate, TemplateGalleryService $gallery)
    {
        $idCardTemplate->update($request->validated());

        if ($idCardTemplate->is_default) {
            $gallery->activate($idCardTemplate);
        }

        return redirect()->route('school-admin.id-cards.index')->with('status', __('ID card template updated.'));
    }

    /** Make this the one template used whenever a card is issued without choosing one. */
    public function activateTemplate(IdCardTemplate $idCardTemplate, TemplateGalleryService $gallery)
    {
        $this->authorize('id_card_templates.manage');

        $gallery->activate($idCardTemplate);

        return redirect()->route('school-admin.id-cards.index')->with('status', __(':p1 is now the active ID card template.', ['p1' => $idCardTemplate->name]));
    }

    public function destroyTemplate(IdCardTemplate $idCardTemplate)
    {
        $this->authorize('id_card_templates.manage');

        $idCardTemplate->delete();

        return redirect()->route('school-admin.id-cards.index')->with('status', 'ID card template deleted.');
    }
}
