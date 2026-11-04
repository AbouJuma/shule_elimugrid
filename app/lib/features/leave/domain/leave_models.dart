/// Mirrors App\Http\Controllers\Api\V1\LeaveController.
class LeaveType {
  const LeaveType({required this.id, required this.name, required this.daysPerYear, this.isPaid = true});

  final String id;
  final String name;
  final int daysPerYear;

  /// Unpaid leave is deducted from salary in the payroll run.
  final bool isPaid;

  factory LeaveType.fromJson(Map<String, dynamic> json) => LeaveType(
    id: json['id'] as String,
    name: json['name'] as String,
    daysPerYear: (json['days_per_year'] as num?)?.toInt() ?? 0,
    isPaid: json['is_paid'] as bool? ?? true,
  );
}

class LeaveRequestItem {
  const LeaveRequestItem({
    required this.id,
    this.type,
    required this.startDate,
    required this.endDate,
    required this.days,
    this.reason,
    required this.status,
    this.requestedBy,
  });

  final String id;
  final String? type;
  final String startDate;
  final String endDate;
  final num days;
  final String? reason;
  final String status;
  final String? requestedBy;

  factory LeaveRequestItem.fromJson(Map<String, dynamic> json) => LeaveRequestItem(
    id: json['id'] as String,
    type: json['type'] as String?,
    startDate: json['start_date'] as String,
    endDate: json['end_date'] as String,
    days: json['days'] as num? ?? 0,
    reason: json['reason'] as String?,
    status: json['status'] as String,
    requestedBy: json['requested_by'] as String?,
  );
}

class LeaveOverview {
  const LeaveOverview({
    required this.types,
    required this.myRequests,
    required this.canApprove,
    required this.pending,
  });

  final List<LeaveType> types;
  final List<LeaveRequestItem> myRequests;
  final bool canApprove;
  final List<LeaveRequestItem> pending;

  factory LeaveOverview.fromJson(Map<String, dynamic> json) {
    List<T> list<T>(String key, T Function(Map<String, dynamic>) f) =>
        (json[key] as List<dynamic>? ?? const []).map((e) => f(e as Map<String, dynamic>)).toList();

    return LeaveOverview(
      types: list('types', LeaveType.fromJson),
      myRequests: list('my_requests', LeaveRequestItem.fromJson),
      canApprove: json['can_approve'] as bool? ?? false,
      pending: list('pending', LeaveRequestItem.fromJson),
    );
  }
}
