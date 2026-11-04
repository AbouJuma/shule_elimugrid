/// Mirrors App\Http\Resources\GuardianResource. Uses the plain numeric
/// `id` (this resource exposes both `id` and `uuid`, but there's no
/// single-guardian show/route-model-binding endpoint to worry about
/// matching — only index/store exist).
class GuardianModel {
  const GuardianModel({
    required this.id,
    required this.name,
    required this.phone,
    this.email,
    this.occupation,
    this.address,
    this.hasLogin = false,
  });

  final int id;
  final String name;
  final String phone;
  final String? email;
  final String? occupation;
  final String? address;

  /// A parent login exists (User linked through guardians.user_id).
  final bool hasLogin;

  factory GuardianModel.fromJson(Map<String, dynamic> json) => GuardianModel(
    id: json['id'] as int,
    name: json['name'] as String,
    phone: json['phone'] as String,
    email: json['email'] as String?,
    occupation: json['occupation'] as String?,
    address: json['address'] as String?,
    hasLogin: json['has_login'] as bool? ?? false,
  );
}
