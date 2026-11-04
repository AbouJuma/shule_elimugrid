import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../../core/providers.dart';
import '../../../shared/forms/entity_form.dart';
import '../domain/guardian_model.dart';

/// Add a guardian, or edit an existing one's contact details.
class GuardianFormScreen extends ConsumerWidget {
  const GuardianFormScreen({super.key, this.guardian});

  final GuardianModel? guardian;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final g = guardian;

    return EntityFormScreen(
      title: g == null ? 'Add guardian' : 'Edit guardian',
      fields: [
        FieldSpec(key: 'name', label: 'Full name', required: true, initial: g?.name, maxLength: 255),
        FieldSpec(key: 'phone', label: 'Phone', kind: FieldKind.phone, required: true, initial: g?.phone, maxLength: 30),
        FieldSpec(key: 'email', label: 'Email', kind: FieldKind.email, initial: g?.email),
        FieldSpec(key: 'occupation', label: 'Occupation', initial: g?.occupation),
        FieldSpec(key: 'address', label: 'Address', kind: FieldKind.multiline, initial: g?.address, maxLength: 1000),
      ],
      onSubmit: (v) async {
        final data = {
          'name': v['name'],
          'phone': v['phone'],
          'email': (v['email'] ?? '').isEmpty ? null : v['email'],
          'occupation': (v['occupation'] ?? '').isEmpty ? null : v['occupation'],
          'address': (v['address'] ?? '').isEmpty ? null : v['address'],
        };
        final api = ref.read(apiClientProvider);

        if (g == null) {
          await api.post('/guardians', data: data);
        } else {
          await api.put('/guardians/${g.id}', data: data);
        }
      },
    );
  }
}
