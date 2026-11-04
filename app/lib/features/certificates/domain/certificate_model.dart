/// Mirrors App\Http\Resources\CertificateResource. Note the certificate's
/// own `id` is a plain integer (Certificate has no uuid route key), while
/// the nested student's `id` is a uuid string — matches how the backend
/// resource actually serializes each one, not a uniform convention.
class CertificateStudent {
  const CertificateStudent({required this.id, required this.name, required this.admissionNumber});

  final String id;
  final String name;
  final String admissionNumber;

  factory CertificateStudent.fromJson(Map<String, dynamic> json) => CertificateStudent(
    id: json['id'] as String,
    name: json['name'] as String,
    admissionNumber: json['admission_number'] as String,
  );
}

class CertificateModel {
  const CertificateModel({
    required this.id,
    required this.certificateNumber,
    required this.title,
    this.achievement,
    this.issuedDate,
    required this.status,
    this.revokedAt,
    this.revocationReason,
    this.student,
    this.template,
    this.issuedBy,
    required this.verificationUrl,
    required this.pdfUrl,
  });

  final int id;
  final String certificateNumber;
  final String title;
  final String? achievement;
  final String? issuedDate;
  final String status;
  final String? revokedAt;
  final String? revocationReason;
  final CertificateStudent? student;
  final String? template;
  final String? issuedBy;
  final String verificationUrl;
  final String pdfUrl;

  bool get isValid => status == 'valid';

  factory CertificateModel.fromJson(Map<String, dynamic> json) => CertificateModel(
    id: json['id'] as int,
    certificateNumber: json['certificate_number'] as String,
    title: json['title'] as String,
    achievement: json['achievement'] as String?,
    issuedDate: json['issued_date'] as String?,
    status: json['status'] as String,
    revokedAt: json['revoked_at'] as String?,
    revocationReason: json['revocation_reason'] as String?,
    student: json['student'] != null
        ? CertificateStudent.fromJson(json['student'] as Map<String, dynamic>)
        : null,
    template: json['template'] as String?,
    issuedBy: json['issued_by'] as String?,
    verificationUrl: json['verification_url'] as String,
    pdfUrl: json['pdf_url'] as String,
  );
}
