/// Single place where the backend base URL is configured.
///
/// Production default below. For local development against a Laravel
/// server running on the host machine, use:
///   const String apiBaseUrl = 'http://10.0.2.2:8000';
/// (10.0.2.2 is the Android emulator's alias for the host loopback.)
library;

const String apiBaseUrl = 'https://earnplus.example.com';

// ---------------------------------------------------------------------------
// Coin economy — ONE source of truth.
//
// The owner may run the app at "100 Coins = ₹1" or "10 Coins = ₹1".
// Change ONLY this constant; every screen derives its coin↔₹ displays
// from [coinsToRupees] / [rupeesToCoins] below. Nothing else in the app
// may hardcode a coin↔rupee conversion factor.
// ---------------------------------------------------------------------------

/// Coins credited per ₹1 of value.
const int coinsPerRupee = 100;

/// Converts a coin balance to its rupee value.
double coinsToRupees(int coins) => coins / coinsPerRupee;

/// Converts a rupee amount to coins (rounded to whole coins).
int rupeesToCoins(double rupees) => (rupees * coinsPerRupee).round();

/// Human-friendly "1,500 coins (≈ ₹15.00)" label for coin amounts.
String coinsValueLabel(int coins) {
  final rupees = coinsToRupees(coins);
  return '$coins coins (≈ ₹${rupees.toStringAsFixed(2)})';
}

// ---------------------------------------------------------------------------
// Interstitial ads — ONE source of truth.
//
// The Unity interstitial (placement from backend /api/config) is shown
// automatically every [interstitialIntervalMinutes] of app FOREGROUND time
// (see InterstitialScheduler). Change ONLY this constant.
// ---------------------------------------------------------------------------

/// Minutes of foreground app time between automatic interstitial ads.
const int interstitialIntervalMinutes = 10;
