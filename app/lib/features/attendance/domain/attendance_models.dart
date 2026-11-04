/// Mirrors App\Http\Controllers\Api\V1\AttendanceController's JSON shape.
/// Note: `id` here is the plain numeric class primary key, not a uuid —
/// this endpoint's request and response were built consistently on the
/// numeric id from the start (see FILE_PLAN's uuid/route-key bug writeup
/// for why that pairing matters), so no mismatch here.
class AttendanceClass {
  const AttendanceClass({required this.id, required this.name, this.subjects = const []});

  final int id;
  final String name;
  final List<AttendanceSubject> subjects;

  factory AttendanceClass.fromJson(Map<String, dynamic> json) => AttendanceClass(
    id: json['id'] as int,
    name: json['name'] as String,
    subjects: (json['subjects'] as List<dynamic>? ?? [])
        .map((s) => AttendanceSubject.fromJson(s as Map<String, dynamic>))
        .toList(),
  );
}

/// A subject the class offers — picked as a "By Subject" tab on the
/// register screen, alongside the default "By Class" (whole-day) one.
class AttendanceSubject {
  const AttendanceSubject({required this.id, required this.name});

  final int id;
  final String name;

  factory AttendanceSubject.fromJson(Map<String, dynamic> json) =>
      AttendanceSubject(id: json['id'] as int, name: json['name'] as String);
}

/// A student's attendance status. `null` (via [AttendanceStatus.unmarked])
/// means "not recorded yet for this date" — distinct from any real value.
enum AttendanceStatus {
  unmarked,
  present,
  absent,
  late,
  excused;

  static AttendanceStatus fromApi(String? value) => switch (value) {
    'present' => AttendanceStatus.present,
    'absent' => AttendanceStatus.absent,
    'late' => AttendanceStatus.late,
    'excused' => AttendanceStatus.excused,
    _ => AttendanceStatus.unmarked,
  };

  String? toApi() => this == AttendanceStatus.unmarked ? null : name;

  String get label => switch (this) {
    AttendanceStatus.unmarked => 'Unmarked',
    AttendanceStatus.present => 'Present',
    AttendanceStatus.absent => 'Absent',
    AttendanceStatus.late => 'Late',
    AttendanceStatus.excused => 'Excused',
  };
}

class RegisterEntry {
  const RegisterEntry({
    required this.studentId,
    required this.name,
    required this.admissionNumber,
    required this.status,
  });

  final int studentId;
  final String name;
  final String admissionNumber;
  final AttendanceStatus status;

  RegisterEntry copyWith({AttendanceStatus? status}) => RegisterEntry(
    studentId: studentId,
    name: name,
    admissionNumber: admissionNumber,
    status: status ?? this.status,
  );

  factory RegisterEntry.fromJson(Map<String, dynamic> json) => RegisterEntry(
    studentId: json['id'] as int,
    name: json['name'] as String,
    admissionNumber: json['admission_number'] as String,
    status: AttendanceStatus.fromApi(json['status'] as String?),
  );
}

class AttendanceRegister {
  const AttendanceRegister({
    required this.classId,
    required this.className,
    required this.date,
    required this.subjectId,
    required this.locked,
    required this.lockedBy,
    required this.canUnlock,
    required this.students,
  });

  final int classId;
  final String className;
  final String date;
  // Null = the whole-day/class register; set = a per-subject one.
  final int? subjectId;

  // A register locks itself the instant it's first saved — locked, it's
  // read-only until someone holding attendance.unlock (canUnlock) frees it.
  final bool locked;
  final String? lockedBy;
  final bool canUnlock;
  final List<RegisterEntry> students;

  factory AttendanceRegister.fromJson(Map<String, dynamic> json) => AttendanceRegister(
    classId: (json['school_class'] as Map<String, dynamic>)['id'] as int,
    className: (json['school_class'] as Map<String, dynamic>)['name'] as String,
    date: json['date'] as String,
    subjectId: json['subject_id'] as int?,
    locked: json['locked'] as bool? ?? false,
    lockedBy: json['locked_by'] as String?,
    canUnlock: json['can_unlock'] as bool? ?? false,
    students: (json['students'] as List<dynamic>)
        .map((s) => RegisterEntry.fromJson(s as Map<String, dynamic>))
        .toList(),
  );
}
