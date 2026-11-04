/// Mirrors App\Http\Resources\IdCardResource. Same id-type asymmetry as
/// CertificateResource: the card's own `id` is a plain integer, the
/// nested student's `id` is a uuid string.
class IdCardStudent {
  const IdCardStudent({required this.id, required this.name, required this.admissionNumber});

  final String id;
  final String name;
  final String admissionNumber;

  factory IdCardStudent.fromJson(Map<String, dynamic> json) => IdCardStudent(
    id: json['id'] as String,
    name: json['name'] as String,
    admissionNumber: json['admission_number'] as String,
  );
}

class IdCardModel {
  const IdCardModel({
    required this.id,
    required this.cardNumber,
    required this.status,
    this.issuedDate,
    this.revokedAt,
    this.revocationReason,
    this.student,
    this.issuedBy,
    required this.verificationUrl,
    required this.pdfUrl,
  });

  final int id;
  final String cardNumber;
  final String status;
  final String? issuedDate;
  final String? revokedAt;
  final String? revocationReason;
  final IdCardStudent? student;
  final String? issuedBy;
  final String verificationUrl;
  final String pdfUrl;

  bool get isActive => status == 'active';

  factory IdCardModel.fromJson(Map<String, dynamic> json) => IdCardModel(
    id: json['id'] as int,
    cardNumber: json['card_number'] as String,
    status: json['status'] as String,
    issuedDate: json['issued_date'] as String?,
    revokedAt: json['revoked_at'] as String?,
    revocationReason: json['revocation_reason'] as String?,
    student: json['student'] != null ? IdCardStudent.fromJson(json['student'] as Map<String, dynamic>) : null,
    issuedBy: json['issued_by'] as String?,
    verificationUrl: json['verification_url'] as String,
    pdfUrl: json['pdf_url'] as String,
  );
}
