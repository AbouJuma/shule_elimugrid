import 'package:flutter/material.dart';
import '../../core/i18n/app_localizations.dart';

class AppTextField extends StatefulWidget {
  const AppTextField({
    super.key,
    required this.label,
    this.controller,
    this.obscureText = false,
    this.isPassword = false,
    this.keyboardType,
    this.errorText,
    this.autofillHints,
    this.onSubmitted,
    this.textInputAction,
  });

  final String label;
  final TextEditingController? controller;
  final bool obscureText;

  /// When true, the field starts obscured and shows an eye icon that
  /// toggles visibility — use this instead of [obscureText] for password
  /// inputs.
  final bool isPassword;

  final TextInputType? keyboardType;
  final String? errorText;
  final Iterable<String>? autofillHints;
  final ValueChanged<String>? onSubmitted;
  final TextInputAction? textInputAction;

  @override
  State<AppTextField> createState() => _AppTextFieldState();
}

class _AppTextFieldState extends State<AppTextField> {
  late bool _obscured = widget.isPassword || widget.obscureText;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: widget.controller,
      obscureText: _obscured,
      keyboardType: widget.keyboardType,
      autofillHints: widget.autofillHints,
      textInputAction: widget.textInputAction,
      onSubmitted: widget.onSubmitted,
      decoration: InputDecoration(
        labelText: context.tr(widget.label),
        errorText: widget.errorText,
        suffixIcon: widget.isPassword
            ? IconButton(
                icon: Icon(_obscured ? Icons.visibility_off : Icons.visibility),
                tooltip: context.tr(_obscured ? 'Show password' : 'Hide password'),
                onPressed: () => setState(() => _obscured = !_obscured),
              )
            : null,
      ),
    );
  }
}
