import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/i18n/app_localizations.dart';
import '../../../core/network/api_exception.dart';
import '../../../core/providers.dart';
import '../../../shared/forms/entity_form.dart';
import '../../academics/academics_lookups.dart';
import '../domain/vehicle_model.dart';
import 'vehicles_controller.dart';

/// Add or edit a bus. Deleting is offered from the edit screen.
class VehicleFormScreen extends ConsumerWidget {
  const VehicleFormScreen({super.key, this.vehicle});

  final VehicleModel? vehicle;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final drivers = ref.watch(driverOptionsProvider);
    final v = vehicle;

    if (!drivers.hasValue) {
      return Scaffold(appBar: AppBar(title: Text(context.tr('Vehicle'))), body: const Center(child: CircularProgressIndicator()));
    }

    return EntityFormScreen(
      title: v == null ? 'Add vehicle' : 'Edit vehicle',
      actions: [
        if (v != null)
          IconButton(
            tooltip: context.tr('Delete'),
            icon: const Icon(Icons.delete_outline),
            onPressed: () => _delete(context, ref, v),
          ),
      ],
      fields: [
        FieldSpec(key: 'registration_number', label: 'Registration number', required: true, initial: v?.registrationNumber, maxLength: 30),
        FieldSpec(key: 'make', label: 'Make', initial: v?.make),
        FieldSpec(key: 'model', label: 'Model', initial: v?.model),
        FieldSpec(key: 'capacity', label: 'Seats', kind: FieldKind.number, initial: v?.capacity?.toString()),
        FieldSpec(
          key: 'status',
          label: 'Status',
          kind: FieldKind.choice,
          required: true,
          initial: v?.status ?? 'active',
          choices: [
            FieldChoice('active', context.tr('Active')),
            FieldChoice('maintenance', context.tr('In maintenance')),
            FieldChoice('inactive', context.tr('Inactive')),
          ],
        ),
        FieldSpec(
          key: 'driver_user_id',
          label: 'Driver',
          kind: FieldKind.choice,
          initial: v?.driver?.id,
          choices: [for (final d in drivers.requireValue) FieldChoice(d.id, d.name)],
        ),
      ],
      onSubmit: (f) async {
        final data = {
          'registration_number': f['registration_number'],
          'make': (f['make'] ?? '').isEmpty ? null : f['make'],
          'model': (f['model'] ?? '').isEmpty ? null : f['model'],
          'capacity': int.tryParse(f['capacity'] ?? ''),
          'status': f['status'],
          'driver_user_id': (f['driver_user_id'] ?? '').isEmpty ? null : f['driver_user_id'],
        };
        final api = ref.read(apiClientProvider);

        if (v == null) {
          await api.post('/vehicles', data: data);
        } else {
          await api.put('/vehicles/${v.id}', data: data);
        }

        ref.invalidate(vehiclesListProvider);
        if (v != null) ref.invalidate(vehicleDetailProvider(v.id));
      },
    );
  }

  Future<void> _delete(BuildContext context, WidgetRef ref, VehicleModel v) async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(context.tr('Delete this vehicle?')),
        content: Text(v.registrationNumber),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context, false), child: Text(context.tr('Cancel'))),
          FilledButton(onPressed: () => Navigator.pop(context, true), child: Text(context.tr('Delete'))),
        ],
      ),
    );
    if (ok != true || !context.mounted) return;

    try {
      await ref.read(apiClientProvider).delete('/vehicles/${v.id}');
      ref.invalidate(vehiclesListProvider);
      if (context.mounted) {
        Navigator.of(context)
          ..pop(true) // the form
          ..maybePop(); // the vehicle detail behind it no longer exists
      }
    } catch (e) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e is ApiException ? (e.fieldErrors?.values.firstOrNull?.firstOrNull ?? e.message) : '$e')));
      }
    }
  }
}
