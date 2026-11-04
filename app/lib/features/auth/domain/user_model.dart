/// Mirrors App\Http\Resources\UserResource on the backend.
class UserSchool {
  const UserSchool({required this.id, required this.name, required this.status});

  final String id;
  final String name;
  final String status;

  factory UserSchool.fromJson(Map<String, dynamic> json) => UserSchool(
    id: json['id'] as String,
    name: json['name'] as String,
    status: json['status'] as String,
  );
}

class UserModel {
  const UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    required this.status,
    this.school,
    required this.roles,
    required this.permissions,
    required this.isPlatformUser,
    this.locale,
  });

  final String id;
  final String name;
  final String email;
  final String? phone;
  final String status;
  final UserSchool? school;
  final List<String> roles;
  final List<String> permissions;
  final bool isPlatformUser;
  final String? locale;

  bool can(String permission) => permissions.contains(permission);

  bool hasRole(String role) => roles.contains(role);

  factory UserModel.fromJson(Map<String, dynamic> json) => UserModel(
    id: json['id'] as String,
    name: json['name'] as String,
    email: json['email'] as String,
    phone: json['phone'] as String?,
    status: json['status'] as String,
    school: json['school'] != null
        ? UserSchool.fromJson(json['school'] as Map<String, dynamic>)
        : null,
    roles: List<String>.from(json['roles'] as List? ?? const []),
    permissions: List<String>.from(json['permissions'] as List? ?? const []),
    isPlatformUser: json['is_platform_user'] as bool? ?? false,
    locale: json['locale'] as String?,
  );
}
