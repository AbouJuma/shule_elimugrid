/// Mirrors App\Http\Resources\ExamResource — plain numeric ids throughout
/// (this resource was never built with a uuid-as-id mismatch, unlike the
/// bug documented in FILE_PLAN for Student/User/Invoice/Payment/School).
class ExamSubject {
  const ExamSubject({required this.id, required this.name, required this.maxMarks});

  final int id;
  final String name;
  final num maxMarks;

  factory ExamSubject.fromJson(Map<String, dynamic> json) => ExamSubject(
    id: json['id'] as int,
    name: json['name'] as String,
    maxMarks: json['max_marks'] as num,
  );
}

/// One of the classes an exam is run for (e.g. a shared "Mid-Term" spans
/// several). Entering marks always means picking one of these first.
class ExamClassRef {
  const ExamClassRef({required this.id, required this.name});

  final int id;
  final String name;

  factory ExamClassRef.fromJson(Map<String, dynamic> json) =>
      ExamClassRef(id: json['id'] as int, name: json['name'] as String);
}

class ExamModel {
  const ExamModel({
    required this.id,
    required this.name,
    required this.status,
    this.startDate,
    this.endDate,
    this.classes = const [],
    this.subjects = const [],
  });

  final int id;
  final String name;
  final String status;
  final String? startDate;
  final String? endDate;
  final List<ExamClassRef> classes;
  final List<ExamSubject> subjects;

  String get classNames => classes.map((c) => c.name).join(', ');

  factory ExamModel.fromJson(Map<String, dynamic> json) => ExamModel(
    id: json['id'] as int,
    name: json['name'] as String,
    status: json['status'] as String,
    startDate: json['start_date'] as String?,
    endDate: json['end_date'] as String?,
    classes: (json['classes'] as List<dynamic>? ?? const [])
        .map((c) => ExamClassRef.fromJson(c as Map<String, dynamic>))
        .toList(),
    subjects: (json['subjects'] as List<dynamic>? ?? const [])
        .map((s) => ExamSubject.fromJson(s as Map<String, dynamic>))
        .toList(),
  );
}

class MarkEntry {
  const MarkEntry({
    required this.studentId,
    required this.name,
    required this.admissionNumber,
    this.marksObtained,
  });

  final int studentId;
  final String name;
  final String admissionNumber;
  final num? marksObtained;

  MarkEntry copyWith({num? Function()? marksObtained}) => MarkEntry(
    studentId: studentId,
    name: name,
    admissionNumber: admissionNumber,
    marksObtained: marksObtained != null ? marksObtained() : this.marksObtained,
  );

  factory MarkEntry.fromJson(Map<String, dynamic> json) => MarkEntry(
    studentId: json['id'] as int,
    name: json['name'] as String,
    admissionNumber: json['admission_number'] as String,
    marksObtained: _parseMarks(json['marks_obtained']),
  );

  // Laravel's `decimal` Eloquent cast always serializes to a JSON string
  // (e.g. "87.50", to preserve precision), never a native number — so
  // this has to parse a string, not just accept a num.
  static num? _parseMarks(Object? value) => switch (value) {
    null => null,
    num n => n,
    String s => num.tryParse(s),
    _ => null,
  };
}

class MarksRegister {
  const MarksRegister({
    required this.examId,
    required this.examName,
    required this.subjectId,
    required this.subjectName,
    required this.classId,
    required this.className,
    required this.maxMarks,
    required this.students,
  });

  final int examId;
  final String examName;
  final int subjectId;
  final String subjectName;
  final int classId;
  final String className;
  final num maxMarks;
  final List<MarkEntry> students;

  factory MarksRegister.fromJson(Map<String, dynamic> json) => MarksRegister(
    examId: (json['exam'] as Map<String, dynamic>)['id'] as int,
    examName: (json['exam'] as Map<String, dynamic>)['name'] as String,
    subjectId: (json['subject'] as Map<String, dynamic>)['id'] as int,
    subjectName: (json['subject'] as Map<String, dynamic>)['name'] as String,
    classId: (json['school_class'] as Map<String, dynamic>)['id'] as int,
    className: (json['school_class'] as Map<String, dynamic>)['name'] as String,
    maxMarks: json['max_marks'] as num,
    students: (json['students'] as List<dynamic>)
        .map((s) => MarkEntry.fromJson(s as Map<String, dynamic>))
        .toList(),
  );
}
