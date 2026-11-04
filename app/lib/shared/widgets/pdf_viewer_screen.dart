import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:printing/printing.dart';
import '../../core/i18n/app_localizations.dart';

/// Full-screen PDF preview with the print/share actions from `printing`.
/// [loader] fetches the bytes (via the authenticated ApiClient), so the same
/// screen serves report cards, payslips, certificates, anything.
class PdfViewerScreen extends StatelessWidget {
  const PdfViewerScreen({super.key, required this.title, required this.loader});

  final String title;
  final Future<Uint8List> Function() loader;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text(context.tr(title))),
      body: PdfPreview(
        build: (format) => loader(),
        canChangeOrientation: false,
        canChangePageFormat: false,
        dynamicLayout: false,
        allowPrinting: true,
        allowSharing: true,
        pdfFileName: '$title.pdf',
      ),
    );
  }
}

void openPdf(BuildContext context, {required String title, required Future<Uint8List> Function() loader}) {
  Navigator.of(context).push(MaterialPageRoute(builder: (_) => PdfViewerScreen(title: title, loader: loader)));
}
