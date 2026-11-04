import '../../attendance/domain/attendance_models.dart' show AttendanceStatus;

class StaffAttendanceEntry {
  const StaffAttendanceEntry({
    required this.userId,
    required this.name,
    required this.employeeNumber,
    required this.jobTitle,
    required this.status,
  });

  final int userId;
  final String name;
  final String? employeeNumber;
  final String? jobTitle;
  final AttendanceStatus status;

  StaffAttendanceEntry copyWith({AttendanceStatus? status}) => StaffAttendanceEntry(
    userId: userId,
    name: name,
    employeeNumber: employeeNumber,
    jobTitle: jobTitle,
    status: status ?? this.status,
  );

  factory StaffAttendanceEntry.fromJson(Map<String, dynamic> json) => StaffAttendanceEntry(
    userId: json['id'] as int,
    name: json['name'] as String,
    employeeNumber: json['employee_number'] as String?,
    jobTitle: json['job_title'] as String?,
    status: AttendanceStatus.fromApi(json['status'] as String?),
  );
}

class StaffAttendanceRegister {
  const StaffAttendanceRegister({
    required this.date,
    required this.locked,
    required this.lockedBy,
    required this.canUnlock,
    required this.staff,
  });

  final String date;
  final bool locked;
  final String? lockedBy;
  final bool canUnlock;
  final List<StaffAttendanceEntry> staff;

  factory StaffAttendanceRegister.fromJson(Map<String, dynamic> json) => StaffAttendanceRegister(
    date: json['date'] as String,
    locked: json['locked'] as bool? ?? false,
    lockedBy: json['locked_by'] as String?,
    canUnlock: json['can_unlock'] as bool? ?? false,
    staff: (json['staff'] as List<dynamic>).map((s) => StaffAttendanceEntry.fromJson(s as Map<String, dynamic>)).toList(),
  );
}
