/// KYC verification state.
///
/// BACKEND STATUS: the Laravel backend has NO KYC endpoints yet (no mobile
/// API routes, no kyc tables as of Phase 13). This service therefore keeps
/// the submission as local draft state and marks it "under review" after
/// the user submits, so the full UI flow is usable today.
///
/// INTEGRATION POINT: when the backend ships `POST /api/kyc` (multipart:
/// document_type, document_number, full_name, dob, front_photo, back_photo)
/// and `GET /api/kyc/status`, replace [submit] / [_refreshFromServer] with
/// real ApiClient calls and map the server status onto [KycStatus]. The
/// screens observe only this service, so no UI changes will be needed.
library;

import 'package:flutter/foundation.dart';

enum KycDocumentType { aadhaar, pan }

enum KycStatus { notStarted, draft, underReview, verified, rejected }

extension KycDocumentTypeLabel on KycDocumentType {
  String get label =>
      this == KycDocumentType.aadhaar ? 'Aadhaar Card' : 'PAN Card';
  String get hint => this == KycDocumentType.aadhaar
      ? '12-digit Aadhaar number'
      : '10-character PAN (e.g. ABCDE1234F)';
}

class KycService extends ChangeNotifier {
  KycDocumentType _type = KycDocumentType.aadhaar;
  String _fullName = '';
  String _documentNumber = '';
  String _dob = '';
  String? _frontPhotoPath;
  String? _backPhotoPath;
  KycStatus _status = KycStatus.notStarted;
  String _reviewNote = '';
  DateTime? _submittedAt;

  KycDocumentType get type => _type;
  String get fullName => _fullName;
  String get documentNumber => _documentNumber;
  String get dob => _dob;
  String? get frontPhotoPath => _frontPhotoPath;
  String? get backPhotoPath => _backPhotoPath;
  KycStatus get status => _status;
  String get reviewNote => _reviewNote;
  DateTime? get submittedAt => _submittedAt;

  bool get hasDraft =>
      _status == KycStatus.draft || _fullName.isNotEmpty;

  void setType(KycDocumentType t) {
    if (_status == KycStatus.underReview || _status == KycStatus.verified) {
      return;
    }
    _type = t;
    _documentNumber = '';
    if (_status == KycStatus.notStarted) _status = KycStatus.draft;
    notifyListeners();
  }

  void saveDetails({
    required String fullName,
    required String documentNumber,
    required String dob,
  }) {
    _fullName = fullName.trim();
    _documentNumber = documentNumber.replaceAll(' ', '').toUpperCase();
    _dob = dob;
    if (_status == KycStatus.notStarted) _status = KycStatus.draft;
    notifyListeners();
  }

  void setFrontPhoto(String path) {
    _frontPhotoPath = path;
    if (_status == KycStatus.notStarted) _status = KycStatus.draft;
    notifyListeners();
  }

  void setBackPhoto(String path) {
    _backPhotoPath = path;
    if (_status == KycStatus.notStarted) _status = KycStatus.draft;
    notifyListeners();
  }

  /// Validates the document number format for the selected type.
  static String? validateNumber(KycDocumentType type, String raw) {
    final v = raw.replaceAll(' ', '').toUpperCase();
    if (type == KycDocumentType.aadhaar) {
      if (!RegExp(r'^[0-9]{12}$').hasMatch(v)) {
        return 'Enter the 12-digit Aadhaar number';
      }
    } else {
      if (!RegExp(r'^[A-Z]{5}[0-9]{4}[A-Z]$').hasMatch(v)) {
        return 'PAN looks like ABCDE1234F';
      }
    }
    return null;
  }

  bool get canSubmit =>
      _fullName.trim().isNotEmpty &&
      validateNumber(_type, _documentNumber) == null &&
      _dob.isNotEmpty &&
      _frontPhotoPath != null &&
      _backPhotoPath != null &&
      (_status == KycStatus.draft || _status == KycStatus.rejected);

  /// Submits the KYC application. Local-only until the backend endpoint
  /// exists (see file header); the status becomes "under review".
  Future<void> submit() async {
    if (!canSubmit) {
      throw StateError('KYC form is incomplete');
    }
    // TODO(api): POST /api/kyc multipart; on 200 map response to status.
    _status = KycStatus.underReview;
    _submittedAt = DateTime.now();
    _reviewNote = '';
    notifyListeners();
  }

  void reset() {
    _type = KycDocumentType.aadhaar;
    _fullName = '';
    _documentNumber = '';
    _dob = '';
    _frontPhotoPath = null;
    _backPhotoPath = null;
    _status = KycStatus.notStarted;
    _reviewNote = '';
    _submittedAt = null;
    notifyListeners();
  }
}
