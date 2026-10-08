<?php

namespace App\Services\Ads;

use App\Models\AdNetwork;
use Illuminate\Http\Request;

/**
 * Contract for mobile ad-network server-to-server reward verifiers.
 *
 * Returns null when verification fails. On success returns:
 * ['user_id' => int, 'transaction_id' => string, 'coins' => int]
 */
interface S2sVerifier
{
    public function verify(AdNetwork $network, Request $request): ?array;
}
