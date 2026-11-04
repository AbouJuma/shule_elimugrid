<?php

namespace App\Http\Controllers\Web;

use App\Http\Requests\Communications\SendSmsRequest;
use App\Http\Requests\Communications\StoreMessageTemplateRequest;
use App\Http\Requests\Communications\UpdateMessageTemplateRequest;
use App\Models\MessageTemplate;
use App\Models\SchoolClass;
use App\Models\SmsLog;
use App\Models\Student;
use App\Services\SmsService;
use App\Support\Pagination\PageSize;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

class CommunicationController extends \App\Http\Controllers\Controller
{
    public function index(Request $request)
    {
        $this->authorize('communications.view');

        $school = app(TenantContext::class)->assertBound();
        app(\App\Services\MessageTemplateService::class)->ensureDefaults($school);
        $all = MessageTemplate::query()->orderByRaw('event IS NULL')->orderBy('event')->orderBy('locale')->orderBy('name')->get();

        $logs = SmsLog::query()
            ->with('student')
            ->when($request->string('status')->isNotEmpty(), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->string('category')->isNotEmpty(), fn ($q) => $q->where('category', $request->string('category')))
            ->latest()
            ->paginate(PageSize::resolve($request))
            ->withQueryString();

        return view('school-admin.communications.index', [
            'logs' => $logs,
            'templates' => $all->whereNull('event')->values(),
            'systemTemplates' => $all->whereNotNull('event')->values(),
            'previews' => $all->whereNotNull('event')->mapWithKeys(fn ($t) => [$t->id => app(\App\Services\FeeMessageService::class)->preview($school, $t->event, $t->locale)])->all(),
            'events' => config('message_templates.events'),
            'tokenHelp' => config('message_templates.tokens'),
            'requiredTokens' => config('message_templates.required_tokens'),
            'students' => Student::query()->where('status', 'active')->orderBy('first_name')->get(),
            'classes' => SchoolClass::query()->orderBy('name')->get(),
        ]);
    }

    public function store(SendSmsRequest $request, SmsService $sms)
    {
        $data = $request->validated();
        $category = $data['category'] ?? 'general';

        if ($data['recipient_type'] === 'student') {
            $student = Student::findOrFail($data['student_id']);
            $sms->sendToStudent($student, $data['message'], $category, $request->user());

            return redirect()->route('school-admin.communications.index')
                ->with('status', __('Message queued for :p1\'s guardian.', ['p1' => $student->fullName()]));
        }

        $class = SchoolClass::findOrFail($data['school_class_id']);
        $students = Student::where('school_class_id', $class->id)->where('status', 'active')->get();
        $result = $sms->sendToStudents($students, $data['message'], $category, $request->user());

        $status = __('Message queued for :n guardian(s) in :class.', ['n' => $result['sent']->count(), 'class' => $class->name]);
        if ($result['skipped']->isNotEmpty()) {
            $status .= ' '.__('Skipped :n student(s) with no guardian phone on file.', ['n' => $result['skipped']->count()]);
        }

        return redirect()->route('school-admin.communications.index')->with('status', $status);
    }

    public function storeTemplate(StoreMessageTemplateRequest $request, TenantContext $tenant)
    {
        MessageTemplate::create(['school_id' => $tenant->id(), ...$request->validated(), 'locale' => $request->validated('locale') ?? 'en']);

        return redirect()->route('school-admin.communications.index')->with('status', 'Template created.');
    }

    public function updateTemplate(UpdateMessageTemplateRequest $request, MessageTemplate $messageTemplate)
    {
        $data = $request->validated();

        if (! $messageTemplate->isSystem()) {
            $data['locale'] = $data['locale'] ?? $messageTemplate->locale;
        }

        $messageTemplate->update($data);

        return redirect()->route('school-admin.communications.index')->with('status', __('Template updated.'));
    }

    public function destroyTemplate(MessageTemplate $messageTemplate)
    {
        $this->authorize('communications.manage_templates');

        if ($messageTemplate->isSystem()) {
            return redirect()->route('school-admin.communications.index')->withErrors(['template' => __('Automatic messages cannot be deleted — edit their wording instead.')]);
        }

        $messageTemplate->delete();

        return redirect()->route('school-admin.communications.index')->with('status', 'Template deleted.');
    }

    /** Put an automatic message back to the wording it shipped with. */
    public function resetTemplate(MessageTemplate $messageTemplate)
    {
        $this->authorize('communications.manage_templates');

        $default = $messageTemplate->isSystem() ? config("message_templates.events.{$messageTemplate->event}.bodies.{$messageTemplate->locale}") : null;
        abort_if($default === null, 404);

        $messageTemplate->update(['body' => $default]);

        return redirect()->route('school-admin.communications.index')->with('status', __('Message reset to its default wording.'));
    }
}
