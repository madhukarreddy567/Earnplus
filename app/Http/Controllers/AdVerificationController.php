<?php

namespace App\Http\Controllers;

use App\Models\AdNetwork;
use App\Services\Ads\AdVerificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Server-to-server rewarded-ad callbacks from mobile SDK networks.
 *
 * Accepts GET and POST (AdMob SSV and Unity S2S both call with GET).
 * No session auth — trust comes from the network's cryptographic
 * signature (AdMob RSA SSV / Unity HMAC), verified before any credit.
 * CSRF-exempt by design (see bootstrap/app.php).
 */
class AdVerificationController extends Controller
{
    public function __construct(protected AdVerificationService $verification)
    {
    }

    public function handle(Request $request, AdNetwork $network): JsonResponse
    {
        $result = $this->verification->handle($network, $request);
        $http = $result['http'];
        unset($result['http']);

        return response()->json($result, $http);
    }
}
