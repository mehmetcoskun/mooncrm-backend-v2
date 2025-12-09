<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LeadWebhookController extends Controller
{
    public function verify(Request $request)
    {
        $verifyToken = config('services.facebook.verify_token');
        $challenge = $request->query('hub_challenge');
        $token = $request->query('hub_verify_token');

        if ($token === $verifyToken) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();

        $entries = $payload['entry'] ?? [];

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];

            foreach ($changes as $change) {
                if (($change['field'] ?? null) !== 'leadgen') {
                    continue;
                }

                $value = $change['value'] ?? [];
                $leadId = $value['leadgen_id'] ?? null;
                $formId = $value['form_id'] ?? null;

                if (!$leadId || !$formId) {
                    continue;
                }

                $category = Category::where('lead_form_id', $formId)->first();

                if (!$category) {
                    continue;
                }

                $setting = Setting::where('organization_id', $category->organization_id)->first();
                $accessToken = $setting?->facebook_settings['access_token'] ?? null;

                if (!$accessToken) {
                    continue;
                }

                $graphVersion = config('services.facebook.graph_version');

                try {
                    $response = Http::get("https://graph.facebook.com/{$graphVersion}/{$leadId}", [
                        'access_token' => $accessToken,
                        'fields' => 'ad_name,adset_name,campaign_name,field_data,created_time',
                    ]);
                } catch (\Throwable $exception) {
                    continue;
                }

                if (!$response->successful()) {
                    continue;
                }

                $leadData = $response->json();

                $fieldData = collect($leadData['field_data'] ?? [])->mapWithKeys(function ($field) {
                    $name = $field['name'] ?? null;
                    $values = $field['values'] ?? [];
                    $value = is_array($values) ? ($values[0] ?? null) : $values;

                    if (!$name) {
                        return [];
                    }

                    return [$name => $value];
                });

                $customerData = [
                    'organization_id' => $category->organization_id,
                    'category_id' => $category->id,
                    'ad_name' => $leadData['ad_name'] ?? null,
                    'adset_name' => $leadData['adset_name'] ?? null,
                    'campaign_name' => $leadData['campaign_name'] ?? null,
                    'lead_form_id' => $formId,
                ];

                $fieldMappings = $category->field_mappings ?? [];

                foreach ($fieldMappings as $mapping) {
                    $fieldKey = $mapping['field_key'] ?? null;
                    $mapTo = $mapping['map_to'] ?? null;

                    if (!$fieldKey || !$mapTo) {
                        continue;
                    }

                    if (!$fieldData->has($fieldKey)) {
                        continue;
                    }

                    $fieldValue = $fieldData->get($fieldKey);

                    if ($mapTo === 'notes') {
                        $fieldLabel = $mapping['field_label'] ?? null;
                        $noteLine = $fieldLabel ? ($fieldLabel . ': ' . $fieldValue) : $fieldValue;

                        if (!empty($customerData['notes'])) {
                            $customerData['notes'] .= PHP_EOL . $noteLine;
                        } else {
                            $customerData['notes'] = $noteLine;
                        }

                        continue;
                    }

                    $customerData[$mapTo] = $fieldValue;
                }

                try {
                    app(CustomerController::class)->handleCustomerEntry($customerData);
                } catch (\Throwable $exception) {
                }
            }
        }

        return response()->json(['status' => 'ok']);
    }
}