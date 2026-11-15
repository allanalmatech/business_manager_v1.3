import 'dart:io';

import 'package:flutter/material.dart';

import '../../app.dart';
import '../../core/result.dart';
import '../../domain/models/catalog.dart';
import '../../widgets/common.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key, required this.onSignedIn});

  final VoidCallback onSignedIn;

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final TextEditingController _user = TextEditingController();
  final TextEditingController _pass = TextEditingController();
  final GlobalKey<FormState> _form = GlobalKey<FormState>();
  bool _busy = false;
  bool _showPassword = false;
  String? _error;

  @override
  void dispose() {
    _user.dispose();
    _pass.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_form.currentState?.validate() ?? false)) return;
    setState(() {
      _busy = true;
      _error = null;
    });

    final AppServices s = AppScope.of(context);
    final Result<Session> res = await s.auth.signIn(_user.text.trim(), _pass.text, _deviceName());
    if (!mounted) return;

    if (res is Err<Session>) {
      setState(() {
        _busy = false;
        _error = res.offline
            ? 'Cannot reach the shop server. Check the phone is online, then try again.'
            : res.message;
      });
      return;
    }
    widget.onSignedIn();
  }

  String _deviceName() {
    try {
      return Platform.isAndroid ? 'Android phone' : (Platform.isIOS ? 'iPhone' : 'Phone');
    } catch (_) {
      return 'Phone';
    }
  }

  @override
  Widget build(BuildContext context) {
    final ColorScheme c = Theme.of(context).colorScheme;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Form(
                key: _form,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: <Widget>[
                    Container(
                      width: 64,
                      height: 64,
                      decoration: BoxDecoration(color: c.primary, borderRadius: BorderRadius.circular(18)),
                      child: Icon(Icons.storefront, color: c.onPrimary, size: 32),
                    ),
                    const SizedBox(height: 20),
                    Text('Business Manager', style: Theme.of(context).textTheme.headlineSmall),
                    const SizedBox(height: 4),
                    Text('Sign in to start selling', style: TextStyle(color: c.onSurfaceVariant)),
                    const SizedBox(height: 28),
                    TextFormField(
                      controller: _user,
                      autofillHints: const <String>[AutofillHints.username],
                      textInputAction: TextInputAction.next,
                      decoration: const InputDecoration(labelText: 'Username', prefixIcon: Icon(Icons.person_outline)),
                      validator: (String? v) => (v == null || v.trim().isEmpty) ? 'Enter your username' : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _pass,
                      obscureText: !_showPassword,
                      autofillHints: const <String>[AutofillHints.password],
                      textInputAction: TextInputAction.done,
                      onFieldSubmitted: (_) => _busy ? null : _submit(),
                      decoration: InputDecoration(
                        labelText: 'Password',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          onPressed: () => setState(() => _showPassword = !_showPassword),
                          icon: Icon(_showPassword ? Icons.visibility_off_outlined : Icons.visibility_outlined),
                          tooltip: _showPassword ? 'Hide password' : 'Show password',
                        ),
                      ),
                      validator: (String? v) => (v == null || v.isEmpty) ? 'Enter your password' : null,
                    ),
                    if (_error != null) ...<Widget>[
                      const SizedBox(height: 16),
                      Container(
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: c.errorContainer,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Row(
                          children: <Widget>[
                            Icon(Icons.error_outline, color: c.onErrorContainer, size: 20),
                            const SizedBox(width: 10),
                            Expanded(child: Text(_error!, style: TextStyle(color: c.onErrorContainer))),
                          ],
                        ),
                      ),
                    ],
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: _busy ? null : _submit,
                      child: _busy
                          ? const SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2))
                          : const Text('Sign in'),
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Sign in once while you have a connection. After that the app keeps working without one.',
                      textAlign: TextAlign.center,
                      style: TextStyle(fontSize: 12, color: c.onSurfaceVariant),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
