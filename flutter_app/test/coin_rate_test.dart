import 'package:earnplus/config.dart';
import 'package:flutter_test/flutter_test.dart';

/// The coin economy has ONE source of truth: [coinsPerRupee].
/// These tests pin the contract so a future rate change is deliberate.
void main() {
  group('coin economy', () {
    test('default rate is 100 coins = ₹1', () {
      expect(coinsPerRupee, 100);
    });

    test('coinsToRupees derives from the constant', () {
      expect(coinsToRupees(100), 1.0);
      expect(coinsToRupees(9000), 90.0);
      expect(coinsToRupees(50), 0.5);
    });

    test('rupeesToCoins derives from the constant', () {
      expect(rupeesToCoins(1), 100);
      expect(rupeesToCoins(90), 9000);
    });

    test('coinsValueLabel shows both units', () {
      expect(coinsValueLabel(1500), '1500 coins (≈ ₹15.00)');
    });
  });
}
