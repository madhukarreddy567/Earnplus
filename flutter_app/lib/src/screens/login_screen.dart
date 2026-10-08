/// Login screen.
///
/// • "Continue with Google" is shown ONLY when /api/config says
///   google_auth_enabled. The Google idToken is sent to POST /api/auth/google.
/// • The email form is shown ONLY when email_auth_enabled (default OFF on the
///   backend, so it is hidden for most users).
library;

import 'package:flutter/material.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:provider/provider.dart';

import '../api/api_client.dart';
import '../auth/auth_service.dart';
import '../config/app_config.dart';
import '../theme.dart';
import 'home_screen.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _emailCtrl = TextEditingController();
  final _passCtrl = TextEditingController();
  String? _formError;
  bool _googleBusy = false;

  @override
  void dispose() {
    _emailCtrl.dispose();
    _passCtrl.dispose();
    super.dispose();
  }

  Future<void> _googleLogin() async {
    final config = Provider.of<AppConfigService>(context, listen: false).config;
    final auth = Provider.of<AuthService>(context, listen: false);
    setState(() {
      _googleBusy = true;
      _formError = null;
    });
    try {
      final signIn = GoogleSignIn(
        serverClientId: config?.googleClientIdAndroid,
      );
      final account = await signIn.signIn();
      if (account == null) {
        throw const ApiException(0, 'Google sign-in was cancelled.');
      }
      final authentication = await account.authentication;
      final idToken = authentication.idToken;
      if (idToken == null || idToken.isEmpty) {
        throw const ApiException(0, 'Google sign-in returned no id token.');
      }
      await auth.loginWithGoogle(idToken);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const HomeScreen()),
      );
    } on ApiException catch (e) {
      setState(() => _formError = e.message);
    } catch (e) {
      setState(() => _formError = 'Google sign-in failed: $e');
    } finally {
      if (mounted) setState(() => _googleBusy = false);
    }
  }

  Future<void> _emailLogin() async {
    final auth = Provider.of<AuthService>(context, listen: false);
    setState(() => _formError = null);
    try {
      await auth.loginWithEmail(
          _emailCtrl.text.trim(), _passCtrl.text);
      if (!mounted) return;
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => const HomeScreen()),
      );
    } on ApiException catch (e) {
      setState(() => _formError = e.message);
    } catch (e) {
      setState(() => _formError = 'Login failed: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    final config = context.watch<AppConfigService>().config;
    final auth = context.watch<AuthService>();
    final googleEnabled = config?.googleAuthEnabled ?? false;
    final emailEnabled = config?.emailAuthEnabled ?? false;
    final logoUrl = config?.branding.logo;

    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 24),
                if (logoUrl != null && logoUrl.isNotEmpty)
                  Image.network(logoUrl, height: 90,
                      errorBuilder: (_, __, ___) => _fallbackLogo())
                else
                  _fallbackLogo(),
                const SizedBox(height: 16),
                Text(
                  config?.siteName ?? 'EarnPlus',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                      fontSize: 28, fontWeight: FontWeight.w800),
                ),
                if ((config?.tagline ?? '').isNotEmpty) ...[
                  const SizedBox(height: 6),
                  Text(
                    config!.tagline,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: EarnPlusColors.muted),
                  ),
                ],
                const SizedBox(height: 36),
                if (googleEnabled)
                  OutlinedButton.icon(
                    key: const Key('google_login_button'),
                    onPressed: _googleBusy || auth.busy ? null : _googleLogin,
                    icon: _googleBusy
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2))
                        : const Icon(Icons.g_mobiledata, size: 28),
                    label: const Text('Continue with Google'),
                  ),
                if (emailEnabled) ...[
                  const SizedBox(height: 24),
                  const Row(
                    children: [
                      Expanded(child: Divider()),
                      Padding(
                        padding: EdgeInsets.symmetric(horizontal: 12),
                        child: Text('or',
                            style:
                                TextStyle(color: EarnPlusColors.muted)),
                      ),
                      Expanded(child: Divider()),
                    ],
                  ),
                  const SizedBox(height: 24),
                  TextField(
                    key: const Key('email_field'),
                    controller: _emailCtrl,
                    keyboardType: TextInputType.emailAddress,
                    decoration: const InputDecoration(
                      labelText: 'Email',
                      prefixIcon: Icon(Icons.email_outlined),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    key: const Key('password_field'),
                    controller: _passCtrl,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'Password',
                      prefixIcon: Icon(Icons.lock_outline),
                    ),
                  ),
                  const SizedBox(height: 16),
                  ElevatedButton(
                    key: const Key('email_login_button'),
                    onPressed: auth.busy ? null : _emailLogin,
                    child: auth.busy
                        ? const SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(
                                strokeWidth: 2, color: Colors.white))
                        : const Text('Log In'),
                  ),
                ],
                if (!googleEnabled && !emailEnabled)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 16),
                    child: Text(
                      'Sign-in is not enabled yet. Please check back later.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: EarnPlusColors.muted),
                    ),
                  ),
                if (_formError != null) ...[
                  const SizedBox(height: 16),
                  Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: EarnPlusColors.danger.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Text(_formError!,
                        textAlign: TextAlign.center,
                        style:
                            const TextStyle(color: EarnPlusColors.danger)),
                  ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }

  Widget _fallbackLogo() => Container(
        width: 90,
        height: 90,
        decoration: BoxDecoration(
          color: EarnPlusColors.primary,
          borderRadius: BorderRadius.circular(24),
        ),
        child: const Icon(Icons.monetization_on,
            color: EarnPlusColors.accent, size: 52),
      );
}
