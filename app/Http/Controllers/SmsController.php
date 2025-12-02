<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Segment;
use App\Models\Customer;
use App\Models\SmsTemplate;
use App\Traits\FilterableTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Twilio\Rest\Client;

class SmsController extends Controller
{
    use FilterableTrait;

    public function send(Request $request)
    {
        if (Gate::none(['marketing_SendSms']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $settings = Setting::where('organization_id', $organizationId)->first();
        $smsSettings = json_decode($settings->sms_settings, true);

        try {
            $client = new Client($smsSettings['account_sid'], $smsSettings['auth_token']);

            $client->messages->create(
                $request->customer['phone'],
                [
                    'from' => $smsSettings['phone_number'],
                    'body' => $request->message
                ]
            );

            return response()->json([
                'message' => 'SMS gönderildi'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'SMS gönderimi sırasında bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }

    public function bulkSend(Request $request)
    {
        if (Gate::none(['marketing_BulkSms']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $template = SmsTemplate::findOrFail($request->sms_template_id);

        $query = Customer::where('organization_id', $organizationId)
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        if ($request->has('segment_id') && !empty($request->segment_id)) {
            $segment = Segment::find($request->segment_id);
            if ($segment && $segment->filters) {
                $this->applyFiltersFromArray($query, $segment->filters);
            }
        }

        $this->applyAdvancedFilters($query, $request);

        $customers = $query->get();
        $customerPhones = $customers->pluck('phone')->filter()->unique()->toArray();

        if (empty($customerPhones)) {
            return response()->json(['message' => 'Seçilen filtreler için geçerli telefon numarası olan müşteri bulunamadı'], 404);
        }

        $settings = Setting::where('organization_id', $organizationId)->first();
        $smsSettings = $settings->sms_settings;

        try {
            $client = new Client($smsSettings['account_sid'], $smsSettings['auth_token']);

            $successCount = 0;
            $failCount = 0;

            foreach ($customerPhones as $phone) {
                try {
                    $client->messages->create(
                        $phone,
                        [
                            'from' => $smsSettings['phone_number'],
                            'body' => $template->message
                        ]
                    );
                    $successCount++;
                } catch (\Exception $e) {
                    $failCount++;
                }
            }

            return response()->json([
                'message' => 'SMS\'ler gönderildi',
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'total_count' => count($customerPhones)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'SMS gönderimi sırasında bir hata oluştu: ' . $e->getMessage()
            ], 500);
        }
    }

    protected function applyFiltersFromArray($query, ?array $filters): void
    {
        if (!$filters || !isset($filters['conditions']) || empty($filters['conditions'])) {
            return;
        }

        $conditions = $filters['conditions'];
        $logicalOperator = $filters['logicalOperator'] ?? 'and';

        if ($logicalOperator === 'or') {
            $query->where(function($q) use ($conditions) {
                foreach ($conditions as $index => $condition) {
                    if ($index === 0) {
                        $this->applyConditionToQuery($q, $condition);
                    } else {
                        $q->orWhere(function($subQuery) use ($condition) {
                            $this->applyConditionToQuery($subQuery, $condition);
                        });
                    }
                }
            });
        } else {
            foreach ($conditions as $condition) {
                $this->applyConditionToQuery($query, $condition);
            }
        }
    }

    protected function applyConditionToQuery($query, array $condition): void
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;

        if (!$field || !$operator || $value === null) {
            return;
        }

        $mockRequest = new Request();
        $mockRequest->merge([
            $field => $value,
            "{$field}_operator" => $operator
        ]);

        if (in_array($field, ['created_at', 'updated_at'])) {
            if ($operator === 'between' && is_array($value) && count($value) === 2) {
                $mockRequest->merge([
                    "{$field}_start" => $value[0],
                    "{$field}_end" => $value[1]
                ]);
            }
        }

        $this->applyFilterByField($query, $mockRequest, $field);
    }
}
