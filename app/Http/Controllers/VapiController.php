<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;

class VapiController extends Controller
{
    public function webhook(Request $request)
    {
        $data = $request->all();

        if (isset($data['message']['type']) && $data['message']['type'] === 'end-of-call-report') {
            $this->handleEndOfCallReport($data);
        }

        return response()->json(['status' => 'success'], 200);
    }

    private function handleEndOfCallReport($data)
    {
        try {
            $customerId = null;
            if (isset($data['message']['assistant']['variableValues']['id'])) {
                $customerId = $data['message']['assistant']['variableValues']['id'];
            }

            if (!$customerId) {
                return;
            }

            $customer = Customer::find($customerId);
            if (!$customer) {
                return;
            }

            $notes = $data['message']['analysis']['summary'] ?? '';
            $recordingUrl = $data['message']['recordingUrl'] ?? null;

            if (empty($notes) && empty($recordingUrl)) {
                return;
            }

            $timestamp = $data['message']['timestamp'] ?? null;
            if ($timestamp) {
                $callTime = Carbon::createFromTimestampMs($timestamp);
                $date = $callTime->format('Y-m-d');
                $time = $callTime->format('H:i');
            } else {
                $now = Carbon::now();
                $date = $now->format('Y-m-d');
                $time = $now->format('H:i');
            }

            $newPhoneCall = [
                'date' => $date,
                'time' => $time,
                'notes' => $notes,
                'recording_url' => $recordingUrl,
                'is_ai_call' => true
            ];

            $phoneCalls = $customer->phone_calls ?? [];

            $phoneCalls[] = $newPhoneCall;

            $customer->phone_calls = $phoneCalls;
            $customer->save();

        } catch (\Exception $e) {
        }
    }
}
