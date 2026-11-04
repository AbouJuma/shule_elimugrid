import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/theme/app_spacing.dart';
import '../../../core/theme/app_typography.dart';
import '../../auth/presentation/auth_controller.dart';
import '../domain/trip_models.dart';
import 'route_form_screen.dart';
import 'trip_providers.dart';

/// The school's bus routes; managers can add and edit them.
class RoutesListBody extends ConsumerWidget {
  const RoutesListBody({super.key});

  Future<void> _open(BuildContext context, WidgetRef ref, [TransportRouteModel? route]) async {
    final saved = await Navigator.of(context).push<bool>(MaterialPageRoute(builder: (_) => RouteFormScreen(route: route)));
    if (saved == true) ref.invalidate(routesProvider);
  }

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final canManage = ref.watch(authControllerProvider).value?.user?.can('transport.manage') ?? false;
    final routes = ref.watch(routesProvider);

    return Stack(
      children: [
        RefreshIndicator(
          onRefresh: () => ref.refresh(routesProvider.future),
          child: switch (routes) {
            AsyncData(:final value) when value.isEmpty => ListView(children: [
              Padding(
                padding: const EdgeInsets.all(AppSpacing.xl),
                child: Center(child: Text(context.tr('No routes yet.'), style: AppTypography.bodyMd)),
              ),
            ]),
            AsyncData(:final value) => ListView.separated(
              padding: const EdgeInsets.only(bottom: 96),
              itemCount: value.length,
              separatorBuilder: (_, _) => const Divider(height: 1, color: AppColors.border),
              itemBuilder: (context, i) {
                final r = value[i];

                return ListTile(
                  onTap: canManage ? () => _open(context, ref, r) : null,
                  leading: const CircleAvatar(backgroundColor: AppColors.green100, child: Icon(Icons.alt_route, color: AppColors.green700)),
                  title: Text(r.name, style: AppTypography.bodyLg),
                  subtitle: Text('${r.stops.length} ${context.tr('stops')}${r.status == 'active' ? '' : ' · ${context.tr('Inactive')}'}', style: AppTypography.bodySm),
                  trailing: canManage ? const Icon(Icons.edit_outlined, size: 18) : null,
                );
              },
            ),
            AsyncError(:final error) => ListView(children: [Padding(padding: const EdgeInsets.all(AppSpacing.lg), child: Text('$error', textAlign: TextAlign.center))]),
            _ => const Center(child: CircularProgressIndicator()),
          },
        ),
        if (canManage)
          Positioned(
            right: AppSpacing.md,
            bottom: AppSpacing.md,
            child: FloatingActionButton.extended(
              heroTag: 'add-route',
              onPressed: () => _open(context, ref),
              icon: const Icon(Icons.add),
              label: Text(context.tr('Add route')),
            ),
          ),
      ],
    );
  }
}
