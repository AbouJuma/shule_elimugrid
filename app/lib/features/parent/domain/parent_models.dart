import '../../results/domain/results_models.dart';
import '../../transport/domain/trip_models.dart';

/// Mirrors App\Http\Controllers\Api\V1\ParentController.

class ChildSummary {
  const ChildSummary({required this.id, required this.name, required this.admissionNumber, required this.status, this.className, this.streamName, this.route, this.stop});

  final String id;
  final String name;
  final String admissionNumber;
  final String status;
  final String? className;
  final String? streamName;
  final String? route;
  final String? stop;

  String get classLabel => [?className, ?streamName].join(' · ');

  factory ChildSummary.fromJson(Map<String, dynamic> json) {
    final transport = json['transport'] as Map<String, dynamic>?;

    return ChildSummary(
      id: json['id'] as String,
      name: json['name'] as String,
      admissionNumber: json['admission_number'] as String? ?? '',
      status: json['status'] as String? ?? '',
      className: json['class'] as String?,
      streamName: json['stream'] as String?,
      route: transport?['route'] as String?,
      stop: transport?['stop'] as String?,
    );
  }
}

/// Where the bus is relative to this child's stop.
class MyStopTiming {
  const MyStopTiming({required this.state, this.stopsAway, this.distanceM, this.etaMinutes, this.arrivedAt});

  /// `coming` or `passed`.
  final String state;
  final int? stopsAway;
  final int? distanceM;
  final int? etaMinutes;
  final DateTime? arrivedAt;

  bool get passed => state == 'passed';

  factory MyStopTiming.fromJson(Map<String, dynamic> json) => MyStopTiming(
    state: json['state'] as String,
    stopsAway: (json['stops_away'] as num?)?.toInt(),
    distanceM: (json['distance_m'] as num?)?.toInt(),
    etaMinutes: (json['eta_minutes'] as num?)?.toInt(),
    arrivedAt: json['arrived_at'] != null ? DateTime.parse(json['arrived_at'] as String).toLocal() : null,
  );
}

class ChildBus {
  const ChildBus({required this.assigned, this.running = false, this.route, this.stopName, this.stopId, this.pickupTime, this.dropoffTime, this.vehicle, this.trip, this.mine});

  final bool assigned;
  final bool running;
  final String? route;
  final String? stopName;
  final int? stopId;
  final String? pickupTime;
  final String? dropoffTime;
  final String? vehicle;
  final Trip? trip;
  final MyStopTiming? mine;

  factory ChildBus.fromJson(Map<String, dynamic> json) {
    if (json['assigned'] != true) return const ChildBus(assigned: false);

    final stop = json['stop'] as Map<String, dynamic>?;

    return ChildBus(
      assigned: true,
      running: json['running'] as bool? ?? false,
      route: json['route'] as String?,
      stopName: stop?['name'] as String?,
      stopId: stop?['id'] as int?,
      pickupTime: stop?['pickup_time'] as String?,
      dropoffTime: stop?['dropoff_time'] as String?,
      vehicle: json['vehicle'] as String?,
      trip: json['trip'] != null ? Trip.fromJson(json['trip'] as Map<String, dynamic>) : null,
      mine: json['mine'] != null ? MyStopTiming.fromJson(json['mine'] as Map<String, dynamic>) : null,
    );
  }
}

class ChildInvoice {
  const ChildInvoice({required this.id, required this.number, this.controlNumber, required this.total, required this.paid, required this.balance, required this.status, this.dueDate});

  final String id;
  final String number;
  final String? controlNumber;
  final double total;
  final double paid;
  final double balance;
  final String status;
  final String? dueDate;

  factory ChildInvoice.fromJson(Map<String, dynamic> json) => ChildInvoice(
    id: json['id'] as String,
    number: json['invoice_number'] as String,
    controlNumber: json['control_number'] as String?,
    total: double.parse('${json['total']}'),
    paid: double.parse('${json['paid']}'),
    balance: double.parse('${json['balance']}'),
    status: json['status'] as String,
    dueDate: json['due_date'] as String?,
  );
}

class ChildFees {
  const ChildFees({required this.currency, required this.total, required this.paid, required this.balance, required this.invoices});

  final String currency;
  final double total;
  final double paid;
  final double balance;
  final List<ChildInvoice> invoices;

  factory ChildFees.fromJson(Map<String, dynamic> json) => ChildFees(
    currency: json['currency'] as String? ?? '',
    total: double.parse('${json['total']}'),
    paid: double.parse('${json['paid']}'),
    balance: double.parse('${json['balance']}'),
    invoices: (json['invoices'] as List<dynamic>).map((i) => ChildInvoice.fromJson(i as Map<String, dynamic>)).toList(),
  );
}

class ChildAttendance {
  const ChildAttendance({required this.days, required this.present, required this.late, required this.absent, required this.excused, this.rate, this.recent = const []});

  final int days;
  final int present;
  final int late;
  final int absent;
  final int excused;
  final int? rate;
  final List<({String date, String status})> recent;

  factory ChildAttendance.fromJson(Map<String, dynamic> json) => ChildAttendance(
    days: (json['days'] as num).toInt(),
    present: (json['present'] as num).toInt(),
    late: (json['late'] as num).toInt(),
    absent: (json['absent'] as num).toInt(),
    excused: (json['excused'] as num).toInt(),
    rate: (json['rate'] as num?)?.toInt(),
    recent: (json['recent'] as List<dynamic>? ?? const []).map((r) => (date: (r as Map<String, dynamic>)['date'] as String, status: r['status'] as String)).toList(),
  );
}

/// The child's termly result — published exams only. Reuses the same
/// [StudentResult]/[SubjectLine] shapes the staff Results module already has.
class ChildResults {
  const ChildResults({required this.published, this.terms = const [], this.term, this.className, this.classSize, this.classAverage, this.examNames = const [], this.result});

  final bool published;
  final List<ResultTerm> terms;
  final ResultTerm? term;
  final String? className;
  final int? classSize;
  final num? classAverage;
  final List<String> examNames;
  final StudentResult? result;

  factory ChildResults.fromJson(Map<String, dynamic> json) {
    final terms = (json['terms'] as List<dynamic>? ?? const []).map((t) => ResultTerm.fromJson(t as Map<String, dynamic>)).toList();
    final termJson = json['term'] as Map<String, dynamic>?;

    if (json['published'] != true) {
      return ChildResults(published: false, terms: terms, term: termJson != null ? ResultTerm(id: termJson['id'] as int, name: termJson['name'] as String, year: termJson['year'] as String?, isCurrent: false) : null);
    }

    return ChildResults(
      published: true,
      terms: terms,
      term: ResultTerm(id: termJson!['id'] as int, name: termJson['name'] as String, year: termJson['year'] as String?, isCurrent: false),
      className: json['class'] as String?,
      classSize: json['class_size'] as int?,
      classAverage: json['class_average'] as num?,
      examNames: (json['exams'] as List<dynamic>? ?? const []).map((e) => (e as Map<String, dynamic>)['name'] as String).toList(),
      result: StudentResult(
        id: '', name: '', admissionNumber: '',
        position: json['position'] as int?, average: json['average'] as num?, total: json['total'] as num?, grade: json['grade'] as String?,
        attendanceRate: json['attendance_rate'] as num?,
        lines: (json['lines'] as List<dynamic>).map((l) => SubjectLine.fromJson(l as Map<String, dynamic>)).toList(),
        pdfUrl: json['pdf_url'] as String? ?? '',
      ),
    );
  }
}
