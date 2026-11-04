/// Mirrors App\Http\Controllers\Api\V1\SuperAdmin\DashboardController and
/// App\Http\Resources\SchoolResource — the platform-wide view a Super Admin
/// gets instead of a school's module grid (they have no school of their own).
class PlatformStats {
  const PlatformStats({
    required this.totalSchools,
    required this.activeSchools,
    required this.pendingSchools,
    required this.suspendedSchools,
    required this.totalStudents,
    required this.totalStaff,
  });

  final int totalSchools;
  final int activeSchools;
  final int pendingSchools;
  final int suspendedSchools;
  final int totalStudents;
  final int totalStaff;

  factory PlatformStats.fromJson(Map<String, dynamic> json) => PlatformStats(
    totalSchools: json['total_schools'] as int,
    activeSchools: json['active_schools'] as int,
    pendingSchools: json['pending_schools'] as int,
    suspendedSchools: json['suspended_schools'] as int,
    totalStudents: json['total_students'] as int,
    totalStaff: json['total_staff'] as int,
  );
}

/// One bar in a small chart — a month's onboarding count, or a package's school count.
class ChartPoint {
  const ChartPoint({required this.label, required this.value});

  final String label;
  final int value;

  factory ChartPoint.fromJson(Map<String, dynamic> json) => ChartPoint(label: json['label'] as String, value: json['value'] as int);
}

class PlatformDashboard {
  const PlatformDashboard({required this.stats, required this.onboardedByMonth, required this.schoolsByPackage, required this.recentSchools});

  final PlatformStats stats;
  final List<ChartPoint> onboardedByMonth;
  final List<ChartPoint> schoolsByPackage;
  final List<SchoolSummary> recentSchools;

  factory PlatformDashboard.fromJson(Map<String, dynamic> json) => PlatformDashboard(
    stats: PlatformStats.fromJson(json['stats'] as Map<String, dynamic>),
    onboardedByMonth: (json['onboarded_by_month'] as List<dynamic>).map((e) => ChartPoint.fromJson(e as Map<String, dynamic>)).toList(),
    schoolsByPackage: (json['schools_by_package'] as List<dynamic>).map((e) => ChartPoint.fromJson(e as Map<String, dynamic>)).toList(),
    recentSchools: (json['recent_schools'] as List<dynamic>).map((e) => SchoolSummary.fromJson(e as Map<String, dynamic>)).toList(),
  );
}

class SchoolSummary {
  const SchoolSummary({
    required this.id,
    required this.name,
    required this.slug,
    required this.status,
    this.packageName,
    this.studentsCount,
    this.email,
    this.phone,
    this.createdAt,
  });

  final String id;
  final String name;
  final String slug;
  final String status;
  final String? packageName;
  final int? studentsCount;
  final String? email;
  final String? phone;
  final DateTime? createdAt;

  bool get isActive => status == 'active';
  bool get isSuspended => status == 'suspended';

  factory SchoolSummary.fromJson(Map<String, dynamic> json) => SchoolSummary(
    id: json['id'] as String,
    name: json['name'] as String,
    slug: json['slug'] as String,
    status: json['status'] as String,
    packageName: (json['subscription_package'] as Map<String, dynamic>?)?['name'] as String?,
    studentsCount: json['students_count'] as int?,
    email: json['email'] as String?,
    phone: json['phone'] as String?,
    createdAt: json['created_at'] != null ? DateTime.parse(json['created_at'] as String).toLocal() : null,
  );
}
