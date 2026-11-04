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

String _iso(DateTime d) => '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';

/// The library catalogue and loans.
class LibraryScreen extends ConsumerWidget {
  const LibraryScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final canManage = ref.watch(authControllerProvider).value?.user?.can('library.manage') ?? false;
    final api = ref.read(apiClientProvider);

    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(title: Text(context.tr('Library')), bottom: TabBar(tabs: [Tab(text: context.tr('Books')), Tab(text: context.tr('Loans'))])),
        body: TabBarView(children: [
          CrudListBody<Map<String, dynamic>>(
            searchHint: 'Search title or author…',
            emptyText: 'No books yet.',
            addLabel: 'Add book',
            load: (q) => api.rows('/library/books', query: {'search': q, 'per_page': 200}),
            onAdd: canManage ? (context) => openForm(context, const BookFormScreen()) : null,
            tile: (context, b, reload) {
              final available = b['available_copies'] as int;

              return ListTile(
                onTap: canManage ? () => _bookActions(context, ref, b, reload) : null,
                leading: const CircleAvatar(backgroundColor: AppColors.navy100, child: Icon(Icons.menu_book_outlined, color: AppColors.navy700)),
                title: Text(b['title'] as String, style: AppTypography.bodyLg),
                subtitle: Text([if (b['author'] != null) b['author'], if (b['category'] != null) b['category']].join(' · '), style: AppTypography.bodySm),
                trailing: StatusPill('$available/${b['total_copies']}', tone: available == 0 ? PillTone.bad : PillTone.good),
              );
            },
          ),
          CrudListBody<Map<String, dynamic>>(
            emptyText: 'No loans yet.',
            load: (_) => api.rows('/library/loans', query: {'per_page': 100}),
            tile: (context, l, reload) {
              final out = l['status'] == 'borrowed';
              final overdue = out && DateTime.tryParse(l['due_date'] as String? ?? '')?.isBefore(DateTime.now().subtract(const Duration(days: 1))) == true;

              return ListTile(
                onTap: canManage && out
                    ? () => showActions(context, title: l['book'] as String, subtitle: l['student'] as String?, actions: [
                          SheetAction(Icons.assignment_turned_in_outlined, 'Mark returned', () async {
                            try {
                              await api.patch('/library/loans/${l['id']}/return');
                              await reload();
                            } catch (e) {
                              if (context.mounted) showError(context, e);
                            }
                          }),
                          SheetAction(Icons.report_gmailerrorred, 'Mark lost', () async {
                            try {
                              await api.patch('/library/loans/${l['id']}/lost');
                              await reload();
                            } catch (e) {
                              if (context.mounted) showError(context, e);
                            }
                          }, destructive: true),
                        ])
                    : null,
                title: Text(l['book'] as String? ?? '—', style: AppTypography.bodyLg),
                subtitle: Text('${l['student']} · ${context.tr('due')} ${l['due_date']}', style: AppTypography.bodySm),
                trailing: StatusPill(
                  context.tr(overdue ? 'Overdue' : (l['status'] as String)[0].toUpperCase() + (l['status'] as String).substring(1)),
                  tone: overdue || l['status'] == 'lost' ? PillTone.bad : (out ? PillTone.warn : PillTone.good),
                ),
              );
            },
          ),
        ]),
      ),
    );
  }

  Future<void> _bookActions(BuildContext context, WidgetRef ref, Map<String, dynamic> b, Future<void> Function() reload) {
    final api = ref.read(apiClientProvider);

    return showActions(context, title: b['title'] as String, subtitle: '${b['available_copies']}/${b['total_copies']} ${context.tr('available')}', actions: [
      SheetAction(Icons.person_add_alt_1_outlined, 'Lend to a student', () async {
        if (await openForm(context, LendBookScreen(book: b))) await reload();
      }),
      SheetAction(Icons.edit_outlined, 'Edit', () async {
        if (await openForm(context, BookFormScreen(book: b))) await reload();
      }),
      SheetAction(Icons.delete_outline, 'Delete', () async {
        if (!await confirmAction(context, title: 'Delete this book?', message: b['title'] as String)) return;

        try {
          await api.delete('/library/books/${b['id']}');
          await reload();
        } catch (e) {
          if (context.mounted) showError(context, e);
        }
      }, destructive: true),
    ]);
  }
}

class BookFormScreen extends ConsumerWidget {
  const BookFormScreen({super.key, this.book});

  final Map<String, dynamic>? book;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final b = book;

    return EntityFormScreen(
      title: b == null ? 'Add book' : 'Edit book',
      fields: [
        FieldSpec(key: 'title', label: 'Title', required: true, initial: b?['title'] as String?, maxLength: 255),
        FieldSpec(key: 'author', label: 'Author', initial: b?['author'] as String?),
        FieldSpec(key: 'isbn', label: 'ISBN', initial: b?['isbn'] as String?),
        FieldSpec(key: 'category', label: 'Category', initial: b?['category'] as String?),
        FieldSpec(key: 'total_copies', label: 'Number of copies', kind: FieldKind.number, required: true, initial: '${b?['total_copies'] ?? 1}'),
      ],
      onSubmit: (v) async {
        String? blank(String k) => (v[k] ?? '').isEmpty ? null : v[k];
        final data = {'title': v['title'], 'author': blank('author'), 'isbn': blank('isbn'), 'category': blank('category'), 'total_copies': int.tryParse(v['total_copies'] ?? '')};
        final api = ref.read(apiClientProvider);

        if (b == null) {
          await api.post('/library/books', data: data);
        } else {
          await api.put('/library/books/${b['id']}', data: data);
        }
      },
    );
  }
}

class LendBookScreen extends ConsumerWidget {
  const LendBookScreen({super.key, required this.book});

  final Map<String, dynamic> book;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final api = ref.read(apiClientProvider);

    return EntityFormScreen(
      title: 'Lend to a student',
      submitLabel: 'Lend book',
      fields: [
        FieldSpec(key: 'student_id', label: 'Student', kind: FieldKind.choice, required: true, search: (q) => searchStudents(api, q)),
        FieldSpec(key: 'due_date', label: 'Due date', kind: FieldKind.date, required: true, initial: _iso(DateTime.now().add(const Duration(days: 14))), firstDate: DateTime.now(), lastDate: DateTime.now().add(const Duration(days: 365))),
      ],
      onSubmit: (v) async => api.post('/library/books/${book['id']}/borrow', data: {'student_id': v['student_id'], 'due_date': v['due_date']}),
    );
  }
}
