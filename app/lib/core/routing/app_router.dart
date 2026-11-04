import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import '../../features/attendance/presentation/attendance_screen.dart';
import '../../features/attendance_scan/presentation/attendance_scan_screen.dart';
import '../../features/staff_attendance/presentation/staff_attendance_screen.dart';
import '../../features/auth/presentation/auth_controller.dart';
import '../../features/auth/presentation/login_screen.dart';
import '../../features/certificates/presentation/certificate_detail_screen.dart';
import '../../features/certificates/presentation/certificates_list_screen.dart';
import '../../features/dashboard/presentation/home_tab_screen.dart';
import '../../features/exams/presentation/exam_detail_screen.dart';
import '../../features/exams/presentation/exams_list_screen.dart';
import '../../features/exams/presentation/marks_entry_screen.dart';
import '../../features/fees/presentation/invoice_detail_screen.dart';
import '../../features/fees/presentation/invoices_list_screen.dart';
import '../../features/guardians/presentation/guardians_list_screen.dart';
import '../../features/id_cards/presentation/id_card_detail_screen.dart';
import '../../features/id_cards/presentation/id_cards_list_screen.dart';
import '../../features/academics/academics_screens.dart';
import '../../features/hostel/hostel_screens.dart';
import '../../features/hr/staff_screens.dart';
import '../../features/inventory/inventory_screens.dart';
import '../../features/leave/presentation/leave_screen.dart';
import '../../features/library/library_screens.dart';
import '../../features/payslips/presentation/payslips_screen.dart';
import '../../features/profile/presentation/profile_screen.dart';
import '../../features/results/presentation/results_screen.dart';
import '../../features/timetable/presentation/timetable_screen.dart';
import '../../features/students/presentation/student_detail_screen.dart';
import '../../features/students/presentation/students_list_screen.dart';
import '../../features/transport/presentation/transport_tab_screen.dart';
import '../../features/transport/presentation/vehicle_detail_screen.dart';
import '../../features/parent/presentation/parent_screens.dart';
import '../../shared/navigation/home_shell.dart';
import '../theme/app_colors.dart';

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: AppColors.navy900,
      body: Center(child: CircularProgressIndicator(color: AppColors.gold500)),
    );
  }
}

/// Bridges Riverpod's authControllerProvider into GoRouter's
/// Listenable-based refresh mechanism, so route redirects re-evaluate
/// whenever auth state changes (login, logout, session restore).
class _AuthRefreshNotifier extends ChangeNotifier {
  _AuthRefreshNotifier(Ref ref) {
    ref.listen(authControllerProvider, (previous, next) => notifyListeners());
  }
}

final _homeNavigatorKey = GlobalKey<NavigatorState>();
final _profileNavigatorKey = GlobalKey<NavigatorState>();

final routerProvider = Provider<GoRouter>((ref) {
  final refreshNotifier = _AuthRefreshNotifier(ref);

  return GoRouter(
    initialLocation: '/splash',
    refreshListenable: refreshNotifier,
    routes: [
      GoRoute(path: '/splash', builder: (context, state) => const _SplashScreen()),
      GoRoute(path: '/login', builder: (context, state) => const LoginScreen()),

      // Just two branches — Home and Profile — matching HomeShell's fixed
      // two-tab bottom nav. Every module lives nested under /home instead
      // of getting its own branch, so the bottom nav stays visible while
      // drilling into a module (it's still the same branch's Navigator).
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) => HomeShell(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(
            navigatorKey: _homeNavigatorKey,
            routes: [
              GoRoute(
                path: '/home',
                builder: (context, state) => const HomeTabScreen(),
                routes: [
                  GoRoute(
                    path: 'students',
                    builder: (context, state) => const StudentsListScreen(),
                    routes: [
                      GoRoute(
                        path: ':id',
                        builder: (context, state) =>
                            StudentDetailScreen(studentId: state.pathParameters['id']!),
                      ),
                    ],
                  ),
                  GoRoute(
                    path: 'transport',
                    builder: (context, state) => const TransportTabScreen(),
                    routes: [
                      GoRoute(
                        path: 'vehicles/:id',
                        builder: (context, state) =>
                            VehicleDetailScreen(vehicleId: state.pathParameters['id']!),
                      ),
                    ],
                  ),
                  GoRoute(path: 'attendance', builder: (context, state) => const AttendanceScreen()),
                  GoRoute(path: 'staff-attendance', builder: (context, state) => const StaffAttendanceScreen()),
                  GoRoute(path: 'attendance-scan', builder: (context, state) => const AttendanceScanScreen()),
                  GoRoute(
                    path: 'exams',
                    builder: (context, state) => const ExamsListScreen(),
                    routes: [
                      GoRoute(
                        path: ':id',
                        builder: (context, state) =>
                            ExamDetailScreen(examId: int.parse(state.pathParameters['id']!)),
                        routes: [
                          GoRoute(
                            path: 'subjects/:subjectId/classes/:classId',
                            builder: (context, state) => MarksEntryScreen(
                              examId: int.parse(state.pathParameters['id']!),
                              subjectId: int.parse(state.pathParameters['subjectId']!),
                              classId: int.parse(state.pathParameters['classId']!),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                  GoRoute(
                    path: 'fees',
                    builder: (context, state) => const InvoicesListScreen(),
                    routes: [
                      GoRoute(
                        path: ':id',
                        builder: (context, state) =>
                            InvoiceDetailScreen(invoiceId: state.pathParameters['id']!),
                      ),
                    ],
                  ),
                  GoRoute(path: 'my-children', builder: (context, state) => const MyChildrenScreen()),
                  GoRoute(path: 'staff', builder: (context, state) => const StaffListScreen()),
                  GoRoute(path: 'inventory', builder: (context, state) => const InventoryScreen()),
                  GoRoute(path: 'library', builder: (context, state) => const LibraryScreen()),
                  GoRoute(path: 'hostel', builder: (context, state) => const HostelScreen()),
                  GoRoute(path: 'academics', builder: (context, state) => const AcademicsScreen()),
                  GoRoute(path: 'timetable', builder: (context, state) => const TimetableScreen()),
                  GoRoute(path: 'results', builder: (context, state) => const ResultsScreen()),
                  GoRoute(path: 'payslips', builder: (context, state) => const PayslipsScreen()),
                  GoRoute(path: 'leave', builder: (context, state) => const LeaveScreen()),
                  GoRoute(path: 'guardians', builder: (context, state) => const GuardiansListScreen()),
                  GoRoute(
                    path: 'certificates',
                    builder: (context, state) => const CertificatesListScreen(),
                    routes: [
                      GoRoute(
                        path: ':id',
                        builder: (context, state) =>
                            CertificateDetailScreen(certificateId: int.parse(state.pathParameters['id']!)),
                      ),
                    ],
                  ),
                  GoRoute(
                    path: 'id-cards',
                    builder: (context, state) => const IdCardsListScreen(),
                    routes: [
                      GoRoute(
                        path: ':id',
                        builder: (context, state) =>
                            IdCardDetailScreen(idCardId: int.parse(state.pathParameters['id']!)),
                      ),
                    ],
                  ),
                ],
              ),
            ],
          ),
          StatefulShellBranch(
            navigatorKey: _profileNavigatorKey,
            routes: [GoRoute(path: '/profile', builder: (context, state) => const ProfileScreen())],
          ),
        ],
      ),
    ],
    redirect: (context, state) {
      final authState = ref.read(authControllerProvider);
      final location = state.matchedLocation;

      if (authState.isLoading) {
        return location == '/splash' ? null : '/splash';
      }

      final isAuthenticated = authState.value?.isAuthenticated ?? false;

      if (!isAuthenticated) {
        return location == '/login' ? null : '/login';
      }

      if (location == '/login' || location == '/splash') {
        return '/home';
      }

      return null;
    },
  );
});
