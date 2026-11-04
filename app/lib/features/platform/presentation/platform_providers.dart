import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../data/platform_repository.dart';
import '../domain/platform_models.dart';

final platformRepositoryProvider = Provider<PlatformRepository>((ref) => PlatformRepository(ref.watch(apiClientProvider)));

final platformDashboardProvider = FutureProvider.autoDispose<PlatformDashboard>((ref) => ref.watch(platformRepositoryProvider).dashboard());

final platformSchoolsProvider = FutureProvider.autoDispose<List<SchoolSummary>>((ref) async =>
    (await ref.watch(platformRepositoryProvider).schools()).items);
