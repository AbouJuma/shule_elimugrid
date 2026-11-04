/// Mirrors App\Http\Resources\StudentResource on the backend.
class StudentClassRef {
  const StudentClassRef({required this.id, required this.name});

  final int id;
  final String name;

  factory StudentClassRef.fromJson(Map<String, dynamic> json) =>
      StudentClassRef(id: json['id'] as int, name: json['name'] as String);
}

class StudentGuardian {
  const StudentGuardian({
    required this.name,
    required this.phone,
    this.email,
    required this.relationship,
    required this.isPrimary,
  });

  final String name;
  final String phone;
  final String? email;
  final String relationship;
  final bool isPrimary;

  factory StudentGuardian.fromJson(Map<String, dynamic> json) => StudentGuardian(
    name: json['name'] as String,
    phone: json['phone'] as String,
    email: json['email'] as String?,
    relationship: json['relationship'] as String,
    isPrimary: json['is_primary'] as bool? ?? false,
  );
}

class StudentModel {
  const StudentModel({
    required this.id,
    required this.admissionNumber,
    this.controlNumber,
    required this.firstName,
    required this.lastName,
    required this.fullName,
    required this.gender,
    this.dateOfBirth,
    this.admissionDate,
    required this.status,
    this.photoUrl,
    this.schoolClass,
    this.stream,
    this.guardians = const [],
    this.hasLogin = false,
  });

  final String id;
  final String admissionNumber;

  /// The permanent fee control number — the same on every invoice, unlike admissionNumber it's fees-only.
  final String? controlNumber;
  final String firstName;
  final String lastName;
  final String fullName;
  final String gender;
  final String? dateOfBirth;
  final String? admissionDate;
  final String status;
  final String? photoUrl;
  final StudentClassRef? schoolClass;
  final StudentClassRef? stream;
  final List<StudentGuardian> guardians;

  /// A login for the student (Student role) already exists.
  final bool hasLogin;

  factory StudentModel.fromJson(Map<String, dynamic> json) => StudentModel(
    id: json['id'] as String,
    admissionNumber: json['admission_number'] as String,
    controlNumber: json['control_number'] as String?,
    firstName: json['first_name'] as String,
    lastName: json['last_name'] as String,
    fullName: json['full_name'] as String,
    gender: json['gender'] as String,
    dateOfBirth: json['date_of_birth'] as String?,
    admissionDate: json['admission_date'] as String?,
    status: json['status'] as String,
    photoUrl: json['photo_url'] as String?,
    schoolClass: json['school_class'] != null
        ? StudentClassRef.fromJson(json['school_class'] as Map<String, dynamic>)
        : null,
    stream: json['stream'] != null
        ? StudentClassRef.fromJson(json['stream'] as Map<String, dynamic>)
        : null,
    guardians: (json['guardians'] as List<dynamic>? ?? const [])
        .map((g) => StudentGuardian.fromJson(g as Map<String, dynamic>))
        .toList(),
    hasLogin: json['has_login'] as bool? ?? false,
  );
}
