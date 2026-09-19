<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;

class FacebookLeadController extends Controller
{
    public function pages(Request $request)
    {
        if (Gate::none(['facebook_lead_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $setting = Setting::where('organization_id', $organizationId)->first();

        if (!$setting || !$setting->facebook_settings || !isset($setting->facebook_settings['access_token'])) {
            return response()->json([
                'data' => [],
            ]);
        }

        $accessToken = $setting->facebook_settings['access_token'];

        try {
            $pagesResponse = Http::get("https://graph.facebook.com/v24.0/me/accounts", [
                'access_token' => $accessToken,
                'fields' => 'id,name,access_token'
            ]);

            if (!$pagesResponse->successful()) {
                return response()->json(['message' => 'Facebook sayfaları alınamadı.'], 400);
            }

            $pagesData = $pagesResponse->json();

            return response()->json([
                'data' => $pagesData['data'] ?? [],
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => "Facebook sayfaları alınırken hata oluştu: " . $e->getMessage()], 500);
        }
    }

    public function forms(Request $request)
    {
        if (Gate::none(['facebook_lead_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $setting = Setting::where('organization_id', $organizationId)->first();

        if (!$setting || !$setting->facebook_settings || !isset($setting->facebook_settings['access_token'])) {
            return response()->json([
                'data' => [],
            ]);
        }

        $accessToken = $setting->facebook_settings['access_token'];
        $pageId = $request->input('page_id');

        if (!$pageId) {
            return response()->json(['message' => 'Page ID gereklidir.'], 400);
        }

        try {
            $pagesResponse = Http::get("https://graph.facebook.com/v24.0/me/accounts", [
                'access_token' => $accessToken,
                'fields' => 'id,name,access_token'
            ]);

            if (!$pagesResponse->successful()) {
                return response()->json(['message' => 'Facebook sayfaları alınamadı.'], 400);
            }

            $pagesData = $pagesResponse->json();
            $page = collect($pagesData['data'] ?? [])->firstWhere('id', $pageId);

            if (!$page) {
                return response()->json(['message' => 'Sayfa bulunamadı.'], 404);
            }

            $pageAccessToken = $page['access_token'] ?? $accessToken;

            // Pagination ile tüm formları al
            $allForms = [];
            $nextUrl = "https://graph.facebook.com/v24.0/{$pageId}/leadgen_forms?" . http_build_query([
                'access_token' => $pageAccessToken,
                'fields' => 'id,name,status',
                'limit' => 100
            ]);

            while ($nextUrl) {
                $formsResponse = Http::get($nextUrl);

                if (!$formsResponse->successful()) {
                    break;
                }

                $formsData = $formsResponse->json();
                
                if (isset($formsData['data']) && is_array($formsData['data'])) {
                    $allForms = array_merge($allForms, $formsData['data']);
                }

                // Sonraki sayfa varsa devam et
                $nextUrl = $formsData['paging']['next'] ?? null;
            }

            $activeForms = array_values(array_filter($allForms, fn($form) => ($form['status'] ?? '') === 'ACTIVE'));

            return response()->json([
                'data' => $activeForms,
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => "Facebook formları alınırken hata oluştu: " . $e->getMessage()], 500);
        }
    }

    public function leads(Request $request)
    {
        if (Gate::none(['facebook_lead_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $setting = Setting::where('organization_id', $organizationId)->first();

        if (!$setting || !$setting->facebook_settings || !isset($setting->facebook_settings['access_token'])) {
            return response()->json([
                'data' => [],
                'paging' => null,
            ]);
        }

        $accessToken = $setting->facebook_settings['access_token'];
        $requestedPageId = $request->input('page_id');
        $requestedFormId = $request->input('form_id');
        $limit = $request->input('limit', 10);
        $after = $request->input('after');
        $before = $request->input('before');

        if (!$requestedPageId || !$requestedFormId) {
            return response()->json([
                'data' => [],
                'paging' => null,
            ]);
        }

        try {
            $pagesResponse = Http::get("https://graph.facebook.com/v24.0/me/accounts", [
                'access_token' => $accessToken,
                'fields' => 'id,name,access_token'
            ]);

            if (!$pagesResponse->successful()) {
                return response()->json(['message' => 'Facebook sayfaları alınamadı.'], 400);
            }

            $pagesData = $pagesResponse->json();

            if (!isset($pagesData['data']) || empty($pagesData['data'])) {
                return response()->json([
                    'data' => [],
                    'paging' => null,
                ]);
            }

            $page = collect($pagesData['data'])->firstWhere('id', $requestedPageId);
            
            if (!$page) {
                return response()->json(['message' => 'Sayfa bulunamadı.'], 404);
            }

            $pageAccessToken = $page['access_token'] ?? $accessToken;
            $pageId = $page['id'];
            $pageName = $page['name'];

            // Pagination ile tüm formları al ve istenen formu bul
            $form = null;
            $nextUrl = "https://graph.facebook.com/v24.0/{$pageId}/leadgen_forms?" . http_build_query([
                'access_token' => $pageAccessToken,
                'fields' => 'id,name,status',
                'limit' => 100
            ]);

            while ($nextUrl && !$form) {
                $formsResponse = Http::get($nextUrl);

                if (!$formsResponse->successful()) {
                    break;
                }

                $formsData = $formsResponse->json();
                $form = collect($formsData['data'] ?? [])->firstWhere('id', $requestedFormId);

                // Sonraki sayfa varsa devam et
                $nextUrl = $formsData['paging']['next'] ?? null;
            }

            if (!$form) {
                return response()->json([
                    'data' => [],
                    'paging' => null,
                ]);
            }

            $formId = $form['id'];
            $formName = $form['name'];

            $leadsParams = [
                'access_token' => $pageAccessToken,
                'fields' => 'id,created_time,field_data,ad_id,ad_name,adset_id,adset_name,campaign_id,campaign_name,form_id,is_organic,platform',
                'limit' => $limit,
            ];

            if ($after) {
                $leadsParams['after'] = $after;
            }

            if ($before) {
                $leadsParams['before'] = $before;
            }

            $leadsResponse = Http::get("https://graph.facebook.com/v24.0/{$formId}/leads", $leadsParams);

            if (!$leadsResponse->successful()) {
                return response()->json([
                    'data' => [],
                    'paging' => null,
                ]);
            }

            $leadsData = $leadsResponse->json();
            $leads = [];

            foreach ($leadsData['data'] ?? [] as $lead) {
                $fieldValues = [];
                if (isset($lead['field_data'])) {
                    foreach ($lead['field_data'] as $field) {
                        $fieldValues[$field['name']] = $field['values'][0] ?? '';
                    }
                }

                $leads[] = [
                    'id' => $lead['id'],
                    'created_time' => $lead['created_time'],
                    'page_id' => $pageId,
                    'page_name' => $pageName,
                    'form_id' => $formId,
                    'form_name' => $formName,
                    'ad_id' => $lead['ad_id'] ?? null,
                    'ad_name' => $lead['ad_name'] ?? null,
                    'adset_id' => $lead['adset_id'] ?? null,
                    'adset_name' => $lead['adset_name'] ?? null,
                    'campaign_id' => $lead['campaign_id'] ?? null,
                    'campaign_name' => $lead['campaign_name'] ?? null,
                    'is_organic' => $lead['is_organic'] ?? false,
                    'platform' => $lead['platform'] ?? null,
                    'field_data' => $fieldValues,
                    'full_name' => $fieldValues['full_name'] ?? $fieldValues['first_name'] ?? $fieldValues['name'] ?? null,
                    'email' => $fieldValues['email'] ?? null,
                    'phone_number' => $fieldValues['phone_number'] ?? $fieldValues['phone'] ?? null,
                ];
            }

            return response()->json([
                'data' => $leads,
                'paging' => $leadsData['paging'] ?? null,
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => "Facebook lead'leri alınırken hata oluştu: " . $e->getMessage()], 500);
        }
    }

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

    public function sendToCrm(Request $request)
    {   
        if (Gate::none(['facebook_lead_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $formId = $request->input('form_id');
        $fieldData = $request->input('field_data', []);
        $adName = $request->input('ad_name');
        $adsetName = $request->input('adset_name');
        $campaignName = $request->input('campaign_name');

        if (!$formId) {
            return response()->json(['message' => 'Form ID gereklidir.'], 400);
        }

        $category = Category::where('lead_form_id', $formId)
            ->where('organization_id', $organizationId)
            ->first();

        if (!$category) {
            return response()->json(['message' => 'Bu form için kategori bulunamadı.'], 404);
        }

        $customerData = [
            'organization_id' => $organizationId,
            'category_id' => $category->id,
            'ad_name' => $adName,
            'adset_name' => $adsetName,
            'campaign_name' => $campaignName,
            'lead_form_id' => $formId,
        ];

        $fieldMappings = $category->field_mappings ?? [];
        $fieldDataCollection = collect($fieldData);

        foreach ($fieldMappings as $mapping) {
            $fieldKey = $mapping['field_key'] ?? null;
            $mapTo = $mapping['map_to'] ?? null;

            if (!$fieldKey || !$mapTo) {
                continue;
            }

            if (!$fieldDataCollection->has($fieldKey)) {
                continue;
            }

            $fieldValue = $fieldDataCollection->get($fieldKey);

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
            return response()->json(['message' => 'Lead başarıyla CRM\'e aktarıldı.']);
        } catch (\Throwable $exception) {
            return response()->json(['message' => 'Lead aktarılırken hata oluştu: ' . $exception->getMessage()], 500);
        }
    }
}
