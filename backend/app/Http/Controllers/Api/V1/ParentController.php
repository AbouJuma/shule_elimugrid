<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fees\InitiateGatewayPaymentRequest;
use App\Http\Resources\TransportTripResource;
use App\Models\AttendanceRecord;
use App\Models\Exam;
use App\Models\Guardian;
use App\Models\Invoice;
use App\Models\Student;
use App\Models\Term;
use App\Models\TimetableEntry;
use App\Models\TransportTrip;
use App\Models\TripLocation;
use App\Services\PaymentGatewayService;
use App\Services\ReportCardService;
use App\Services\TripService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;

/**
 * What a signed-in parent sees: their own children (found through the
 * guardian record linked to their login), and for each child the bus, the
 * fees and the attendance. Access is by that link alone — a parent holds no
 * school-wide permission, so nothing here is reachable for anyone else's child.
 */
class ParentController extends Controller
{
    public function children(Request $request)
    {
        $children = $this->childrenOf($request)->load(['schoolClass', 'stream', 'transportRoute', 'routeStop']);

        return response()->json(['data' => $children->map(fn ($s) => [
            'id' => $s->uuid,
            'name' => $s->fullName(),
            'admission_number' => $s->admission_number,
            'status' => $s->status,
            'class' => $s->schoolClass?->name,
            'stream' => $s->stream?->name,
            'transport' => $s->transportRoute ? ['route' => $s->transportRoute->name, 'stop' => $s->routeStop?->name] : null,
        ])->values()]);
    }

    /** Is the child's bus running, where is it, and when will it reach their stop? */
    public function bus(Request $request, string $student, TripService $trips)
    {
        $child = $this->child($request, $student)->load(['transportRoute.vehicle', 'routeStop']);

        if (! $child->transport_route_id) {
            return response()->json(['data' => ['assigned' => false]]);
        }

        $base = [
            'assigned' => true,
            'route' => $child->transportRoute->name,
            'stop' => $child->routeStop ? ['id' => $child->routeStop->id, 'name' => $child->routeStop->name, 'latitude' => $child->routeStop->latitude !== null ? (float) $child->routeStop->latitude : null, 'longitude' => $child->routeStop->longitude !== null ? (float) $child->routeStop->longitude : null, 'pickup_time' => $child->routeStop->pickup_time, 'dropoff_time' => $child->routeStop->dropoff_time] : null,
            'vehicle' => $child->transportRoute->vehicle?->registration_number,
        ];

        $trip = TransportTrip::query()->where('transport_route_id', $child->transport_route_id)->where('status', 'in_progress')->latest('started_at')->first();

        if (! $trip) {
            return response()->json(['data' => $base + ['running' => false]]);
        }

        $detail = (new TransportTripResource($trip))->detailed()->resolve($request);

        // Parents get the map, the stops and the timing — not other children's counts or the driver's phone.
        $detail['stops'] = collect($detail['stops'])->map(fn ($s) => collect($s)->except('students')->all())->all();
        $detail['vehicle'] = ['registration_number' => $detail['vehicle']['registration_number'], 'driver' => $detail['vehicle']['driver'] ? ['name' => $detail['vehicle']['driver']['name']] : null];
        unset($detail['next']);

        return response()->json(['data' => $base + ['running' => true, 'trip' => $detail, 'mine' => $this->timingFor($trip, $child, $trips)]]);
    }

    public function fees(Request $request, string $student)
    {
        $child = $this->child($request, $student);
        $invoices = Invoice::query()->where('student_id', $child->id)->latest('issued_date')->latest('id')->get();

        return response()->json(['data' => [
            'currency' => $child->school?->currency ?? 'TZS',
            'total' => (string) $invoices->sum('total_amount'),
            'paid' => (string) $invoices->sum('amount_paid'),
            'balance' => (string) round($invoices->sum(fn ($i) => (float) $i->total_amount - (float) $i->amount_paid), 2),
            'invoices' => $invoices->map(fn ($i) => [
                'id' => $i->uuid, 'invoice_number' => $i->invoice_number, 'control_number' => $i->control_number,
                'total' => (string) $i->total_amount, 'paid' => (string) $i->amount_paid,
                'balance' => (string) round((float) $i->total_amount - (float) $i->amount_paid, 2),
                'status' => $i->status, 'due_date' => $i->due_date?->toDateString(),
            ])->values(),
        ]]);
    }

    /** Pay an invoice's outstanding balance (or part of it) by mobile-money push to $msisdn. */
    public function payInvoice(InitiateGatewayPaymentRequest $request, string $student, string $invoice, PaymentGatewayService $gateway)
    {
        $child = $this->child($request, $student);
        $invoiceModel = Invoice::where('uuid', $invoice)->where('student_id', $child->id)->firstOrFail();

        // Bank checkout requires a merchantName; mobile money ignores it.
        // The signed-in guardian's own name, not something worth a form field.
        $transaction = $gateway->initiate($invoiceModel, $request->validated() + ['account_name' => $request->user()->name]);

        return response()->json(['data' => $this->transactionPayload($transaction)]);
    }

    /** Poll this while waiting for the guardian to approve the push on their phone. */
    public function paymentStatus(Request $request, string $student, string $transaction)
    {
        $child = $this->child($request, $student);
        $gatewayTransaction = \App\Models\PaymentGatewayTransaction::where('uuid', $transaction)->where('student_id', $child->id)->firstOrFail();

        return response()->json(['data' => $this->transactionPayload($gatewayTransaction)]);
    }

    protected function transactionPayload(\App\Models\PaymentGatewayTransaction $transaction): array
    {
        return [
            'id' => $transaction->uuid,
            'status' => $transaction->status,
            'amount' => (string) $transaction->amount,
            'failure_reason' => $transaction->failure_reason,
            'receipt_number' => $transaction->payment?->receipt_number,
        ];
    }

    public function attendance(Request $request, string $student)
    {
        $child = $this->child($request, $student);
        // whereNull('subject_id'): the whole-day/class register only — a
        // child can also have per-subject rows for the same date now, and
        // this parent-facing summary isn't a per-subject breakdown.
        $records = AttendanceRecord::query()->where('student_id', $child->id)->where('date', '>=', now()->subDays(30)->toDateString())->whereNull('subject_id')->orderByDesc('date')->get();

        return response()->json(['data' => [
            'days' => $records->count(),
            'present' => $records->where('status', 'present')->count(),
            'late' => $records->where('status', 'late')->count(),
            'absent' => $records->where('status', 'absent')->count(),
            'excused' => $records->where('status', 'excused')->count(),
            'rate' => $records->isEmpty() ? null : (int) round($records->whereIn('status', ['present', 'late'])->count() / $records->count() * 100),
            'recent' => $records->take(10)->map(fn ($r) => ['date' => \Illuminate\Support\Carbon::parse($r->date)->toDateString(), 'status' => $r->status])->values(),
        ]]);
    }

    /**
     * The child's termly result: position, average and each subject, from
     * PUBLISHED exams only (an exam still being marked is not shown to a parent).
     */
    public function results(Request $request, string $student, ReportCardService $reports, TenantContext $tenant)
    {
        $child = $this->child($request, $student);
        $school = $tenant->assertBound();

        if (! $child->school_class_id) {
            return response()->json(['data' => ['terms' => [], 'published' => false]]);
        }

        $termIds = Exam::query()
            ->whereHas('classes', fn ($q) => $q->where('school_classes.id', $child->school_class_id))
            ->where('status', 'published')
            ->pluck('term_id')->unique();
        $terms = Term::query()->with('academicYear')->whereIn('id', $termIds)->orderByDesc('start_date')->get();

        $term = ($request->integer('term_id') ? $terms->firstWhere('id', $request->integer('term_id')) : null)
            ?? $terms->firstWhere('is_current', true)
            ?? $terms->first();

        $termList = $terms->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'year' => $t->academicYear?->name, 'is_current' => (bool) $t->is_current])->values();

        if (! $term) {
            return response()->json(['data' => ['terms' => $termList, 'published' => false]]);
        }

        $data = $reports->cardFor($school, $child->schoolClass, $term, $child, publishedOnly: true);

        if (! $data) {
            return response()->json(['data' => ['terms' => $termList, 'term' => ['id' => $term->id, 'name' => $term->name], 'published' => false]]);
        }

        $card = $data['card'];

        return response()->json(['data' => [
            'published' => true,
            'terms' => $termList,
            'term' => ['id' => $term->id, 'name' => $term->name, 'year' => $term->academicYear?->name],
            'class' => $child->schoolClass->name,
            'class_size' => $data['class_size'],
            'class_average' => $data['class_average'],
            'position' => $card['position'],
            'average' => $card['average'],
            'total' => $card['total'],
            'grade' => $card['grade'],
            'remark' => $card['remark'],
            'attendance_rate' => $card['attendance']['rate'] ?? null,
            'class_teacher_remark' => $card['class_teacher_remark'],
            'head_teacher_remark' => $card['head_teacher_remark'],
            'exams' => $data['exams']->map(fn ($e) => ['id' => $e->id, 'name' => $e->name, 'weight' => (float) $e->weight])->values(),
            'lines' => $card['lines']->whereNotNull('percent')->map(fn ($l) => [
                'subject' => $l['subject'], 'percent' => $l['percent'], 'grade' => $l['grade'], 'remark' => $l['remark'],
                'exam_scores' => $data['exams']->map(fn ($e) => $l['by_exam'][$e->id] ?? null)->values(),
                'class_average' => $l['class_average'] ?? null, 'position' => $l['position'] ?? null,
            ])->values(),
            'pdf_url' => route('school-admin.results.student-pdf', [$child, 'term_id' => $term->id]),
        ]]);
    }

    /** The child's class timetable (their stream's lessons plus lessons shared by the whole class). */
    public function timetable(Request $request, string $student)
    {
        $child = $this->child($request, $student);

        if (! $child->school_class_id) {
            return response()->json(['data' => ['days' => [], 'periods' => [], 'entries' => []]]);
        }

        $entries = TimetableEntry::query()
            ->with(['subject', 'teacher', 'stream', 'schoolClass'])
            ->where('school_class_id', $child->school_class_id)
            ->where(fn ($q) => $child->stream_id
                ? $q->where('stream_id', $child->stream_id)->orWhereNull('stream_id')
                : $q->whereNull('stream_id'))
            ->get();

        return response()->json(['data' => app(TimetableController::class)->payload($entries)]);
    }

    /** ETA to this child's stop, or that the bus has already been / is not on the way to it. */
    protected function timingFor(TransportTrip $trip, Student $child, TripService $trips): ?array
    {
        $stop = $child->routeStop;

        if (! $stop) {
            return null;
        }

        $visited = $trip->visits()->where('route_stop_id', $stop->id)->first();

        if ($visited) {
            return ['state' => 'passed', 'arrived_at' => $visited->arrived_at->toIso8601String()];
        }

        $ordered = $trips->orderedStops($trip);
        $pending = $ordered->filter(fn ($s) => ! $trip->visits()->where('route_stop_id', $s->id)->exists())->values();
        $stopsAway = $pending->search(fn ($s) => $s->id === $stop->id);

        $last = TripLocation::where('transport_trip_id', $trip->id)->latest('id')->first();
        $eta = null;

        if ($last && $stop->latitude !== null && $stop->longitude !== null) {
            // Distance along the remaining stops, in visiting order, up to the child's stop.
            $points = [[$last->latitude, $last->longitude]];
            foreach ($pending as $i => $s) {
                if ($s->latitude !== null) {
                    $points[] = [(float) $s->latitude, (float) $s->longitude];
                }
                if ($i === $stopsAway) {
                    break;
                }
            }

            $metres = 0;
            for ($i = 1; $i < count($points); $i++) {
                $metres += TripService::distanceMetres($points[$i - 1][0], $points[$i - 1][1], $points[$i][0], $points[$i][1]);
            }
            $metres *= 1.3;

            $speeds = TripLocation::where('transport_trip_id', $trip->id)->whereNotNull('speed_kmh')->latest('id')->limit(5)->pluck('speed_kmh')->filter(fn ($v) => $v >= 5);
            $kmh = $speeds->isNotEmpty() ? max(10, min(60, $speeds->avg())) : 25;
            // Each intermediate stop costs the bus about a minute of boarding time.
            $eta = (int) max(1, min(999, ceil($metres / 1000 / $kmh * 60 + max(0, $stopsAway))));

            return ['state' => 'coming', 'stops_away' => (int) $stopsAway, 'distance_m' => (int) round($metres), 'eta_minutes' => $eta];
        }

        return ['state' => 'coming', 'stops_away' => $stopsAway === false ? null : (int) $stopsAway, 'distance_m' => null, 'eta_minutes' => null];
    }

    /** @return \Illuminate\Support\Collection<int, Student> */
    protected function childrenOf(Request $request)
    {
        $students = \App\Support\OwnStudents::query($request->user())->orderBy('first_name')->get();

        abort_if($students->isEmpty(), 403, __('This account is not linked to a guardian or a student.'));

        return $students;
    }

    protected function child(Request $request, string $uuid): Student
    {
        return $this->childrenOf($request)->firstWhere('uuid', $uuid) ?? abort(404);
    }
}
