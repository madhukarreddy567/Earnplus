import 'package:earnplus/src/screens/kyc_screen.dart';
import 'package:earnplus/src/services/kyc_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

Widget _wrap(KycService kyc) =>
    ChangeNotifierProvider<KycService>.value(
      value: kyc,
      child: const MaterialApp(home: KycScreen()),
    );

void main() {
  group('KycService', () {
    test('validates Aadhaar numbers', () {
      expect(
          KycService.validateNumber(
              KycDocumentType.aadhaar, '1234 5678 9012'),
          isNull);
      expect(
          KycService.validateNumber(KycDocumentType.aadhaar, '12345'),
          isNotNull);
      expect(
          KycService.validateNumber(KycDocumentType.aadhaar, 'ABCDEFGHIJKL'),
          isNotNull);
    });

    test('validates PAN numbers', () {
      expect(
          KycService.validateNumber(
              KycDocumentType.pan, 'ABCDE1234F'),
          isNull);
      expect(
          KycService.validateNumber(KycDocumentType.pan, 'abcde1234f'),
          isNull); // case-insensitive
      expect(
          KycService.validateNumber(KycDocumentType.pan, 'ABC123'),
          isNotNull);
    });

    test('submit moves draft to under review', () async {
      final kyc = KycService();
      kyc.setType(KycDocumentType.pan);
      kyc.saveDetails(
          fullName: 'Test User',
          documentNumber: 'ABCDE1234F',
          dob: '01/01/1990');
      kyc.setFrontPhoto('/tmp/front.jpg');
      kyc.setBackPhoto('/tmp/back.jpg');
      expect(kyc.canSubmit, isTrue);
      await kyc.submit();
      expect(kyc.status, KycStatus.underReview);
      expect(kyc.submittedAt, isNotNull);
    });

    test('cannot submit with invalid number', () async {
      final kyc = KycService();
      kyc.saveDetails(
          fullName: 'Test User',
          documentNumber: '123',
          dob: '01/01/1990');
      kyc.setFrontPhoto('/tmp/front.jpg');
      kyc.setBackPhoto('/tmp/back.jpg');
      expect(kyc.canSubmit, isFalse);
      expect(() => kyc.submit(), throwsStateError);
    });

    test('switching type clears the number', () {
      final kyc = KycService();
      kyc.saveDetails(
          fullName: 'Test User',
          documentNumber: '123456789012',
          dob: '01/01/1990');
      kyc.setType(KycDocumentType.pan);
      expect(kyc.documentNumber, isEmpty);
    });
  });

  group('KycScreen', () {
    testWidgets('document type step renders both options', (t) async {
      await t.pumpWidget(_wrap(KycService()));
      await t.pumpAndSettle();
      expect(find.text('Choose your document'), findsOneWidget);
      expect(find.byKey(const Key('kyc_type_aadhaar')), findsOneWidget);
      expect(find.byKey(const Key('kyc_type_pan')), findsOneWidget);
    });

    testWidgets('selecting PAN updates the service', (t) async {
      final kyc = KycService();
      await t.pumpWidget(_wrap(kyc));
      await t.pumpAndSettle();
      await t.tap(find.byKey(const Key('kyc_type_pan')));
      await t.pumpAndSettle();
      expect(kyc.type, KycDocumentType.pan);
    });

    testWidgets('invalid number shows an error on Review', (t) async {
      await t.pumpWidget(_wrap(KycService()));
      await t.pumpAndSettle();
      await t.tap(find.byKey(const Key('kyc_continue_type')));
      await t.pumpAndSettle();
      await t.enterText(find.byKey(const Key('kyc_name')), 'Test User');
      await t.enterText(find.byKey(const Key('kyc_number')), '123');
      // The Review button sits below the fold: scroll it into view first.
      await t.drag(find.byType(ListView).first, const Offset(0, -600));
      await t.pumpAndSettle();
      await t.tap(find.byKey(const Key('kyc_continue_details')));
      await t.pumpAndSettle();
      expect(find.text('Enter the 12-digit Aadhaar number'), findsOneWidget);
    });

    testWidgets('under-review status screen renders', (t) async {
      final kyc = KycService();
      kyc.setType(KycDocumentType.pan);
      kyc.saveDetails(
          fullName: 'Test User',
          documentNumber: 'ABCDE1234F',
          dob: '01/01/1990');
      kyc.setFrontPhoto('/tmp/front.jpg');
      kyc.setBackPhoto('/tmp/back.jpg');
      await kyc.submit();
      await t.pumpWidget(_wrap(kyc));
      await t.pumpAndSettle();
      expect(find.byKey(const Key('kyc_status_title')), findsOneWidget);
      expect(find.text('Under review'), findsWidgets); // title + timeline step
    });
  });
}
