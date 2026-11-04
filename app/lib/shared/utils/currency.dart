import 'package:intl/intl.dart';

final _currencyFormat = NumberFormat('#,##0.00', 'en_US');

/// Thousands-grouped, fixed 2dp — matches the portal's
/// `number_format($amount, 2)` display, without hardcoding a currency
/// symbol (deployment-dependent; TZS for the initial market).
String formatMoney(num amount) => _currencyFormat.format(amount);
