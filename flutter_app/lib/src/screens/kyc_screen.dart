/// KYC verification: document choice → details + photos → review → status.
///
/// Aadhaar (12 digits) and PAN (ABCDE1234F) numbers are validated locally.
/// Photos use image_picker (gallery / camera). Submission state lives in
/// [KycService]; there is no backend endpoint yet (see its file header), so
/// submitting marks the application "under review" locally.
/// Stitch design language.
library;

import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:provider/provider.dart';

import '../services/kyc_service.dart';
import '../stitch/stitch_theme.dart';
import '../stitch/stitch_widgets.dart';

class KycScreen extends StatefulWidget {
  const KycScreen({super.key});

  @override
  State<KycScreen> createState() => _KycScreenState();
}

class _KycScreenState extends State<KycScreen> {
  int _step = 0;
  final _nameCtrl = TextEditingController();
  final _numberCtrl = TextEditingController();
  final _dobCtrl = TextEditingController();
  String? _numberError;
  bool _submitting = false;
  final _picker = ImagePicker();

  @override
  void initState() {
    super.initState();
    final kyc = Provider.of<KycService>(context, listen: false);
    _nameCtrl.text = kyc.fullName;
    _numberCtrl.text = kyc.documentNumber;
    _dobCtrl.text = kyc.dob;
    // Jump straight to status when an application already exists.
    if (kyc.status == KycStatus.underReview ||
        kyc.status == KycStatus.verified) {
      _step = 3;
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _numberCtrl.dispose();
    _dobCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickPhoto(bool front) async {
    final kyc = Provider.of<KycService>(context, listen: false);
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (_) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Take a photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;
    final file = await _picker.pickImage(
        source: source, maxWidth: 1600, imageQuality: 85);
    if (file == null || !mounted) return;
    if (front) {
      kyc.setFrontPhoto(file.path);
    } else {
      kyc.setBackPhoto(file.path);
    }
  }

  Future<void> _pickDob() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: DateTime(now.year - 18, now.month, now.day),
      firstDate: DateTime(now.year - 100),
      lastDate: DateTime(now.year - 18),
      helpText: 'Date of birth (must be 18+)',
    );
    if (picked != null) {
      _dobCtrl.text =
          '${picked.day.toString().padLeft(2, '0')}/${picked.month.toString().padLeft(2, '0')}/${picked.year}';
    }
  }

  void _saveDetails() {
    final kyc = Provider.of<KycService>(context, listen: false);
    final err = KycService.validateNumber(kyc.type, _numberCtrl.text);
    setState(() => _numberError = err);
    if (_nameCtrl.text.trim().isEmpty || err != null || _dobCtrl.text.isEmpty) {
      return;
    }
    kyc.saveDetails(
      fullName: _nameCtrl.text,
      documentNumber: _numberCtrl.text,
      dob: _dobCtrl.text,
    );
    setState(() => _step = 2);
  }

  Future<void> _submit() async {
    final kyc = Provider.of<KycService>(context, listen: false);
    setState(() => _submitting = true);
    try {
      await kyc.submit();
      if (mounted) setState(() => _step = 3);
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: StitchColors.background,
      appBar: stitchAppBar('KYC Verification'),
      body: Consumer<KycService>(
        builder: (context, kyc, _) {
          if (_step == 3) return _buildStatus(kyc);
          return Column(
            children: [
              _buildStepper(),
              Expanded(
                child: ListView(
                  padding: const EdgeInsets.all(20),
                  children: [
                    if (_step == 0) _buildTypeStep(kyc),
                    if (_step == 1) _buildDetailsStep(kyc),
                    if (_step == 2) _buildReviewStep(kyc),
                  ],
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Widget _buildStepper() {
    const labels = ['Document', 'Details', 'Review'];
    return Container(
      color: Colors.white,
      padding: const EdgeInsets.symmetric(vertical: 14, horizontal: 20),
      child: Row(
        children: [
          for (var i = 0; i < labels.length; i++) ...[
            _stepDot(i, _step >= i),
            const SizedBox(width: 8),
            Text(labels[i],
                style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: _step >= i
                        ? StitchColors.primaryDark
                        : StitchColors.muted)),
            if (i < labels.length - 1)
              Expanded(
                child: Container(
                  height: 2,
                  margin: const EdgeInsets.symmetric(horizontal: 8),
                  color: _step > i
                      ? StitchColors.primary
                      : StitchColors.line,
                ),
              ),
          ],
        ],
      ),
    );
  }

  Widget _stepDot(int i, bool active) => Container(
        width: 26,
        height: 26,
        decoration: BoxDecoration(
          color: active ? StitchColors.primary : StitchColors.line,
          shape: BoxShape.circle,
        ),
        child: Center(
          child: Text('${i + 1}',
              style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w800,
                  color: active ? Colors.white : StitchColors.muted)),
        ),
      );

  Widget _buildTypeStep(KycService kyc) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Choose your document', style: StitchText.headline),
          const SizedBox(height: 6),
          const Text(
            'KYC unlocks withdrawals. Your documents stay private and are only used for verification.',
            style: StitchText.caption,
          ),
          const SizedBox(height: 18),
          for (final t in KycDocumentType.values)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: InkWell(
                key: Key('kyc_type_${t.name}'),
                borderRadius: BorderRadius.circular(18),
                onTap: () => kyc.setType(t),
                child: Container(
                  decoration: stitchCardDecoration().copyWith(
                    border: Border.all(
                      color: kyc.type == t
                          ? StitchColors.primary
                          : StitchColors.line,
                      width: kyc.type == t ? 2 : 1,
                    ),
                  ),
                  padding: const EdgeInsets.all(18),
                  child: Row(
                    children: [
                      Container(
                        width: 48,
                        height: 48,
                        decoration: BoxDecoration(
                          color: kyc.type == t
                              ? StitchColors.primarySoft
                              : StitchColors.background,
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Icon(
                          t == KycDocumentType.aadhaar
                              ? Icons.badge_outlined
                              : Icons.credit_card_outlined,
                          color: StitchColors.primaryDark,
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(t.label, style: StitchText.title),
                            const SizedBox(height: 2),
                            Text(t.hint, style: StitchText.caption),
                          ],
                        ),
                      ),
                      Icon(
                        kyc.type == t
                            ? Icons.radio_button_checked
                            : Icons.radio_button_unchecked,
                        color: kyc.type == t
                            ? StitchColors.primary
                            : StitchColors.muted,
                      ),
                    ],
                  ),
                ),
              ),
            ),
          const SizedBox(height: 8),
          stitchPrimaryButton(
            key: const Key('kyc_continue_type'),
            label: 'Continue',
            onPressed: () => setState(() => _step = 1),
          ),
        ],
      );

  Widget _buildDetailsStep(KycService kyc) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('${kyc.type.label} details', style: StitchText.headline),
          const SizedBox(height: 18),
          TextField(
            key: const Key('kyc_name'),
            controller: _nameCtrl,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(
              labelText: 'Full name (as on document)',
              prefixIcon: Icon(Icons.person_outline),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            key: const Key('kyc_number'),
            controller: _numberCtrl,
            textCapitalization: TextCapitalization.characters,
            keyboardType: kyc.type == KycDocumentType.aadhaar
                ? TextInputType.number
                : TextInputType.text,
            decoration: InputDecoration(
              labelText: kyc.type == KycDocumentType.aadhaar
                  ? 'Aadhaar number'
                  : 'PAN number',
              prefixIcon: const Icon(Icons.numbers_outlined),
              errorText: _numberError,
              hintText: kyc.type.hint,
            ),
            onChanged: (_) => setState(() => _numberError = null),
          ),
          const SizedBox(height: 12),
          TextField(
            key: const Key('kyc_dob'),
            controller: _dobCtrl,
            readOnly: true,
            decoration: const InputDecoration(
              labelText: 'Date of birth',
              prefixIcon: Icon(Icons.cake_outlined),
              hintText: 'DD/MM/YYYY',
            ),
            onTap: _pickDob,
          ),
          const SizedBox(height: 20),
          const StitchSectionHeader(title: 'Document photos'),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                  child: _photoTile(
                      'Front', kyc.frontPhotoPath, () => _pickPhoto(true))),
              const SizedBox(width: 12),
              Expanded(
                  child: _photoTile(
                      'Back', kyc.backPhotoPath, () => _pickPhoto(false))),
            ],
          ),
          const SizedBox(height: 20),
          stitchPrimaryButton(
            key: const Key('kyc_continue_details'),
            label: 'Review',
            onPressed: _saveDetails,
          ),
          const SizedBox(height: 8),
          TextButton(
            onPressed: () => setState(() => _step = 0),
            child: const Text('Back',
                style: TextStyle(color: StitchColors.muted)),
          ),
        ],
      );

  Widget _photoTile(String label, String? path, VoidCallback onTap) =>
      InkWell(
        key: Key('kyc_photo_${label.toLowerCase()}'),
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Container(
          height: 130,
          decoration: stitchCardDecoration().copyWith(
            border: Border.all(
              color:
                  path != null ? StitchColors.primary : StitchColors.line,
              width: path != null ? 2 : 1,
            ),
          ),
          child: path != null
              ? ClipRRect(
                  borderRadius: BorderRadius.circular(17),
                  child: Image.file(File(path), fit: BoxFit.cover),
                )
              : Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const Icon(Icons.add_a_photo_outlined,
                        size: 32, color: StitchColors.muted),
                    const SizedBox(height: 6),
                    Text('$label photo', style: StitchText.caption),
                  ],
                ),
        ),
      );

  Widget _buildReviewStep(KycService kyc) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Review & submit', style: StitchText.headline),
          const SizedBox(height: 18),
          Container(
            decoration: stitchCardDecoration(),
            padding: const EdgeInsets.all(18),
            child: Column(
              children: [
                _reviewRow('Document', kyc.type.label),
                _reviewRow('Full name', kyc.fullName),
                _reviewRow('Number', kyc.documentNumber),
                _reviewRow('Date of birth', kyc.dob),
                _reviewRow('Photos',
                    '${kyc.frontPhotoPath != null ? 'Front ✓' : 'Front ✗'} · ${kyc.backPhotoPath != null ? 'Back ✓' : 'Back ✗'}'),
              ],
            ),
          ),
          const SizedBox(height: 12),
          const Text(
            'By submitting you confirm the details match your document. Verification usually takes 24–48 hours.',
            style: StitchText.caption,
          ),
          const SizedBox(height: 20),
          stitchPrimaryButton(
            key: const Key('kyc_submit'),
            label: 'Submit for verification',
            busy: _submitting,
            onPressed: kyc.canSubmit ? _submit : null,
          ),
          const SizedBox(height: 8),
          TextButton(
            onPressed: () => setState(() => _step = 1),
            child: const Text('Back',
                style: TextStyle(color: StitchColors.muted)),
          ),
        ],
      );

  Widget _reviewRow(String label, String value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 7),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(label, style: StitchText.caption),
            Flexible(
              child: Text(value,
                  textAlign: TextAlign.end,
                  style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: StitchColors.neutral)),
            ),
          ],
        ),
      );

  Widget _buildStatus(KycService kyc) {
    final verified = kyc.status == KycStatus.verified;
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        const SizedBox(height: 24),
        Center(
          child: Container(
            width: 88,
            height: 88,
            decoration: BoxDecoration(
              color: verified
                  ? StitchColors.primarySoft
                  : StitchColors.amberSoft,
              shape: BoxShape.circle,
            ),
            child: Icon(
              verified ? Icons.verified : Icons.hourglass_top,
              size: 46,
              color: verified
                  ? StitchColors.primaryDark
                  : StitchColors.secondary,
            ),
          ),
        ),
        const SizedBox(height: 16),
        Text(
          verified ? 'KYC Verified!' : 'Under review',
          key: const Key('kyc_status_title'),
          textAlign: TextAlign.center,
          style: StitchText.headline,
        ),
        const SizedBox(height: 8),
        Text(
          verified
              ? 'Your identity is verified. Withdrawals are unlocked.'
              : 'We received your ${kyc.type.label} on ${kyc.submittedAt != null ? '${kyc.submittedAt!.day}/${kyc.submittedAt!.month}/${kyc.submittedAt!.year}' : ''}. You\'ll be notified once it\'s reviewed.',
          textAlign: TextAlign.center,
          style: StitchText.body,
        ),
        const SizedBox(height: 24),
        _statusStep('Submitted', true),
        _statusStep('Under review', true),
        _statusStep('Verified', verified, last: true),
        const SizedBox(height: 24),
        if (!verified)
          const StitchTrustBadges(badges: [
            (Icons.lock_outline, 'Documents encrypted'),
            (Icons.visibility_off_outlined, 'Never shared'),
          ]),
      ],
    );
  }

  Widget _statusStep(String label, bool done, {bool last = false}) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Column(
            children: [
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  color: done
                      ? StitchColors.primary
                      : StitchColors.line,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  done ? Icons.check : Icons.circle,
                  size: 16,
                  color: done ? Colors.white : StitchColors.muted,
                ),
              ),
              if (!last)
                Container(
                    width: 2, height: 28,
                    color: done
                        ? StitchColors.primary
                        : StitchColors.line),
            ],
          ),
          const SizedBox(width: 12),
          Padding(
            padding: const EdgeInsets.only(top: 4),
            child: Text(label,
                style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: done
                        ? StitchColors.neutral
                        : StitchColors.muted)),
          ),
        ],
      );
}
