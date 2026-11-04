/// Mirrors App\Http\Controllers\Api\V1\ResultsController.
class ResultTerm {
  const ResultTerm({required this.id, required this.name, this.year, required this.isCurrent});

  final int id;
  final String name;
  final String? year;
  final bool isCurrent;

  String get label => year == null ? name : '$name · $year';

  factory ResultTerm.fromJson(Map<String, dynamic> json) => ResultTerm(
    id: json['id'] as int,
    name: json['name'] as String,
    year: json['year'] as String?,
    isCurrent: json['is_current'] as bool? ?? false,
  );
}

class SubjectLine {
  const SubjectLine({required this.subject, this.percent, this.grade, this.classAverage, this.position, this.examScores = const []});

  final String subject;
  final num? percent;
  final String? grade;
  final num? classAverage;
  final int? position;

  /// Score (%) in each exam, aligned with [ClassResults.exams]; null where the student sat none.
  final List<num?> examScores;

  factory SubjectLine.fromJson(Map<String, dynamic> json) => SubjectLine(
    subject: json['subject'] as String,
    percent: json['percent'] as num?,
    grade: json['grade'] as String?,
    classAverage: json['class_average'] as num?,
    position: json['position'] as int?,
    examScores: (json['exam_scores'] as List<dynamic>? ?? const []).map((e) => e as num?).toList(),
  );
}

class StudentResult {
  const StudentResult({
    required this.id,
    required this.name,
    required this.admissionNumber,
    this.position,
    this.average,
    this.total,
    this.grade,
    this.attendanceRate,
    required this.lines,
    required this.pdfUrl,
  });

  final String id;
  final String name;
  final String admissionNumber;
  final int? position;
  final num? average;
  final num? total;
  final String? grade;
  final num? attendanceRate;
  final List<SubjectLine> lines;
  final String pdfUrl;

  factory StudentResult.fromJson(Map<String, dynamic> json) => StudentResult(
    id: json['id'] as String,
    name: json['name'] as String,
    admissionNumber: json['admission_number'] as String? ?? '',
    position: json['position'] as int?,
    average: json['average'] as num?,
    total: json['total'] as num?,
    grade: json['grade'] as String?,
    attendanceRate: json['attendance_rate'] as num?,
    lines: (json['lines'] as List<dynamic>).map((l) => SubjectLine.fromJson(l as Map<String, dynamic>)).toList(),
    pdfUrl: json['pdf_url'] as String,
  );
}

class ClassResults {
  const ClassResults({
    required this.className,
    required this.termName,
    required this.classSize,
    this.classAverage,
    required this.examsCount,
    required this.classPdfUrl,
    required this.students,
    this.exams = const [],
  });

  final String className;
  final String termName;
  final int classSize;
  final num? classAverage;
  final int examsCount;
  final String classPdfUrl;
  final List<StudentResult> students;

  /// Exam names in the order used by every line's `examScores`.
  final List<String> exams;

  factory ClassResults.fromJson(Map<String, dynamic> json) => ClassResults(
    className: (json['class'] as Map<String, dynamic>)['name'] as String,
    termName: (json['term'] as Map<String, dynamic>)['name'] as String,
    classSize: json['class_size'] as int? ?? 0,
    classAverage: json['class_average'] as num?,
    examsCount: json['exams_count'] as int? ?? 0,
    classPdfUrl: json['class_pdf_url'] as String? ?? '',
    students: (json['students'] as List<dynamic>).map((s) => StudentResult.fromJson(s as Map<String, dynamic>)).toList(),
    exams: (json['exams'] as List<dynamic>? ?? const []).map((e) => (e as Map<String, dynamic>)['name'] as String).toList(),
  );
}
