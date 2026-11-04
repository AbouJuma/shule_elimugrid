import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import '../../core/i18n/app_localizations.dart';
import '../../core/providers.dart';
import '../../core/theme/app_colors.dart';
import '../../core/theme/app_typography.dart';
import '../../shared/crud/crud_list.dart';
import '../../shared/forms/entity_form.dart';
import '../academics/academics_lookups.dart';
import '../auth/presentation/auth_controller.dart';

String _num(Object? v) {
  final d = double.tryParse('$v');

  return d == null ? '$v' : (d % 1 == 0 ? d.toInt().toString() : d.toStringAsFixed(2));
}

String _today() {
  final n = DateTime.now();

  return '${n.year.toString().padLeft(4, '0')}-${n.month.toString().padLeft(2, '0')}-${n.day.toString().padLeft(2, '0')}';
}

/// Stock items and categories.
class InventoryScreen extends ConsumerWidget {
  const InventoryScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final canManage = ref.watch(authControllerProvider).value?.user?.can('inventory.manage') ?? false;
    final api = ref.read(apiClientProvider);

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(title: Text(context.tr('Inventory')), bottom: TabBar(tabs: [Tab(text: context.tr('Items')), Tab(text: context.tr('Categories'))])),
        body: TabBarView(children: [
          CrudListBody<Map<String, dynamic>>(
            searchHint: 'Search items…',
            emptyText: 'No items yet.',
            addLabel: 'Add item',
            load: (q) => api.rows('/inventory/items', query: {'search': q, 'per_page': 200}),
            onAdd: canManage ? (context) => openForm(context, const ItemFormScreen()) : null,
            tile: (context, item, reload) => ListTile(
              onTap: () => _itemActions(context, ref, item, reload, canManage),
              leading: const CircleAvatar(backgroundColor: AppColors.navy100, child: Icon(Icons.inventory_2_outlined, color: AppColors.navy700)),
              title: Text(item['name'] as String, style: AppTypography.bodyLg),
              subtitle: Text('${_num(item['quantity_on_hand'])} ${item['unit']}${item['category'] != null ? ' · ${item['category']}' : ''}', style: AppTypography.bodySm),
              trailing: item['low_stock'] == true ? StatusPill(context.tr('Low stock'), tone: PillTone.warn) : null,
            ),
          ),
          CrudListBody<Map<String, dynamic>>(
            emptyText: 'No categories yet.',
            addLabel: 'Add category',
            load: (_) => api.rows('/inventory/categories'),
            onAdd: canManage ? (context) => openForm(context, const CategoryFormScreen()) : null,
            tile: (context, c, reload) => ListTile(
              leading: const CircleAvatar(backgroundColor: AppColors.gold100, child: Icon(Icons.category_outlined, color: AppColors.gold700)),
              title: Text(c['name'] as String, style: AppTypography.bodyLg),
              subtitle: Text('${c['items_count']} ${context.tr('items')}${(c['description'] ?? '') != '' ? ' · ${c['description']}' : ''}', style: AppTypography.bodySm),
            ),
          ),
        ]),
      ),
    );
  }

  Future<void> _itemActions(BuildContext context, WidgetRef ref, Map<String, dynamic> item, Future<void> Function() reload, bool canManage) {
    final api = ref.read(apiClientProvider);

    return showActions(context, title: item['name'] as String, subtitle: '${_num(item['quantity_on_hand'])} ${item['unit']}', actions: [
      if (canManage) ...[
        SheetAction(Icons.add_box_outlined, 'Stock in', () async {
          if (await openForm(context, MovementFormScreen(item: item, type: 'in'))) await reload();
        }),
        SheetAction(Icons.indeterminate_check_box_outlined, 'Stock out', () async {
          if (await openForm(context, MovementFormScreen(item: item, type: 'out'))) await reload();
        }),
        SheetAction(Icons.edit_outlined, 'Edit', () async {
          if (await openForm(context, ItemFormScreen(item: item))) await reload();
        }),
      ],
      SheetAction(Icons.history, 'Stock history', () async {
        try {
          final rows = await api.rows('/inventory/items/${item['id']}/movements');
          if (!context.mounted) return;

          await showModalBottomSheet<void>(
            context: context,
            showDragHandle: true,
            builder: (context) => SafeArea(
              child: rows.isEmpty
                  ? Padding(padding: const EdgeInsets.all(24), child: Text(context.tr('No movements yet.')))
                  : ListView(shrinkWrap: true, children: [
                      for (final m in rows)
                        ListTile(
                          dense: true,
                          leading: Icon(m['type'] == 'in' ? Icons.south_west : Icons.north_east, color: m['type'] == 'in' ? AppColors.green700 : AppColors.error600),
                          title: Text('${m['type'] == 'in' ? '+' : '−'}${_num(m['quantity'])} ${item['unit']}'),
                          subtitle: Text([m['moved_at'], m['reason'], m['recorded_by']].where((e) => e != null && '$e'.isNotEmpty).join(' · ')),
                        ),
                    ]),
            ),
          );
        } catch (e) {
          if (context.mounted) showError(context, e);
        }
      }),
      if (canManage)
        SheetAction(Icons.delete_outline, 'Delete', () async {
          if (!await confirmAction(context, title: 'Delete this item?', message: item['name'] as String)) return;

          try {
            await api.delete('/inventory/items/${item['id']}');
            await reload();
          } catch (e) {
            if (context.mounted) showError(context, e);
          }
        }, destructive: true),
    ]);
  }
}

class ItemFormScreen extends ConsumerWidget {
  const ItemFormScreen({super.key, this.item});

  final Map<String, dynamic>? item;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final api = ref.read(apiClientProvider);

    return FutureBuilder<List<Map<String, dynamic>>>(
      future: api.rows('/inventory/categories'),
      builder: (context, snap) {
        if (!snap.hasData && !snap.hasError) return Scaffold(appBar: AppBar(title: Text(context.tr('Item'))), body: const Center(child: CircularProgressIndicator()));

        final i = item;

        return EntityFormScreen(
          title: i == null ? 'Add item' : 'Edit item',
          fields: [
            FieldSpec(key: 'name', label: 'Item name', required: true, initial: i?['name'] as String?, maxLength: 150),
            FieldSpec(
              key: 'inventory_category_id',
              label: 'Category',
              kind: FieldKind.choice,
              initial: i?['category_id']?.toString(),
              choices: [for (final c in snap.data ?? const <Map<String, dynamic>>[]) FieldChoice('${c['id']}', c['name'] as String)],
            ),
            FieldSpec(key: 'unit', label: 'Unit (box, kg, piece…)', required: true, initial: i?['unit'] as String?, maxLength: 20),
            FieldSpec(key: 'reorder_level', label: 'Reorder level', kind: FieldKind.decimal, initial: i?['reorder_level'] != null ? _num(i!['reorder_level']) : null),
            FieldSpec(key: 'unit_cost', label: 'Cost per unit', kind: FieldKind.decimal, initial: i?['unit_cost'] != null ? _num(i!['unit_cost']) : null),
            if (i == null) const FieldSpec(key: 'opening_quantity', label: 'Quantity in stock now', kind: FieldKind.decimal, initial: '0'),
          ],
          onSubmit: (v) async {
            double? d(String k) => (v[k] ?? '').isEmpty ? null : double.tryParse(v[k]!);

            final data = {
              'name': v['name'],
              'inventory_category_id': int.tryParse(v['inventory_category_id'] ?? ''),
              'unit': v['unit'],
              'reorder_level': d('reorder_level'),
              'unit_cost': d('unit_cost'),
              if (i == null) 'opening_quantity': d('opening_quantity') ?? 0,
            };

            if (i == null) {
              await api.post('/inventory/items', data: data);
            } else {
              await api.put('/inventory/items/${i['id']}', data: data);
            }
          },
        );
      },
    );
  }
}

class MovementFormScreen extends ConsumerWidget {
  const MovementFormScreen({super.key, required this.item, required this.type});

  final Map<String, dynamic> item;
  final String type;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return EntityFormScreen(
      title: type == 'in' ? 'Stock in' : 'Stock out',
      submitLabel: 'Record',
      fields: [
        FieldSpec(key: 'quantity', label: 'Quantity (${item['unit']})', kind: FieldKind.decimal, required: true, serverKey: 'quantity'),
        const FieldSpec(key: 'reason', label: 'Reason (optional)', maxLength: 255),
        FieldSpec(key: 'moved_at', label: 'Date', kind: FieldKind.date, required: true, initial: _today(), lastDate: DateTime.now()),
      ],
      onSubmit: (v) async {
        await ref.read(apiClientProvider).post('/inventory/movements', data: {
          'inventory_item_id': item['id'],
          'type': type,
          'quantity': double.tryParse(v['quantity'] ?? ''),
          if ((v['reason'] ?? '').isNotEmpty) 'reason': v['reason'],
          'moved_at': v['moved_at'],
        });
      },
    );
  }
}

class CategoryFormScreen extends ConsumerWidget {
  const CategoryFormScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return EntityFormScreen(
      title: 'Add category',
      fields: const [
        FieldSpec(key: 'name', label: 'Category name', required: true, maxLength: 100),
        FieldSpec(key: 'description', label: 'Description', maxLength: 255),
      ],
      onSubmit: (v) async => ref.read(apiClientProvider).post('/inventory/categories', data: {
        'name': v['name'],
        if ((v['description'] ?? '').isNotEmpty) 'description': v['description'],
      }),
    );
  }
}
