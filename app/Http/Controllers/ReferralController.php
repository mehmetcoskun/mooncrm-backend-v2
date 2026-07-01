<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ReferralController extends Controller
{
    public function show($token)
    {
        $referrer = Customer::where('referral_token', $token)->first();
        if (!$referrer) {
            return response()->json(null, 404);
        }

        return response()->json([
            'referrer_name' => $referrer->name,
        ]);
    }

    public function submit(Request $request, $token)
    {
        $referrer = Customer::where('referral_token', $token)->first();
        if (!$referrer) {
            return response()->json(null, 404);
        }

        $clientIp = $request->ip();
        $rateKey = "referral_submit_{$clientIp}_{$token}";
        $currentCount = Cache::get($rateKey, 0);

        if ($currentCount >= 1) {
            return response()->json([
                'error' => 'rate_limit_exceeded'
            ], 429);
        }

        Cache::put($rateKey, $currentCount + 1, 60);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
        ]);

        $data = [
            'organization_id' => $referrer->organization_id,
            'user_id' => $referrer->user_id,
            'category_id' => 8,
            'referred_by_customer_id' => $referrer->id,
            'name' => $validated['name'],
            'phone' => $validated['phone'],
            'email' => $validated['email'] ?? null,
        ];

        $customerController = new CustomerController();
        $customerController->handleCustomerEntry($data);

        return response()->json(true);
    }
}
