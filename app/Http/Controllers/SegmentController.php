<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Segment;
use App\Traits\FilterableTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SegmentController extends Controller
{
    use FilterableTrait;

    public function index(Request $request)
    {
        if (Gate::none(['segment_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if ($organizationId) {
            $segments = Segment::where('organization_id', $organizationId);
        } else {
            $segments = Segment::query();
        }

        $user = auth()->user();
        $userRoleIds = $user->roles->pluck('id')->toArray();

        if (in_array(3, $userRoleIds) || in_array(7, $userRoleIds) && !in_array(1, $userRoleIds) && !in_array(2, $userRoleIds)) {
            $segments->where(function($query) use ($user) {
                $query->whereNull('language')
                      ->orWhere('language', '')
                      ->orWhereIn('language', $user->languages);
            });
        }

        $segments = $segments->orderBy('id', 'desc')->get()->load('organization');

        foreach ($segments as $segment) {
            $segment->customer_count = $this->getSegmentCustomerCount($segment, $organizationId);
        }

        return $segments;
    }

    public function store(Request $request)
    {
        if (Gate::none(['segment_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        return Segment::create($data)->load('organization');
    }

    public function update(Request $request, Segment $segment)
    {
        if (Gate::none(['segment_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $segment->update($data);
        $segment->load('organization');

        return response()->json($segment);
    }

    public function destroy(Segment $segment)
    {
        if (Gate::none(['segment_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $segment->delete();
    }

    /**
     * Segment için müşteri sayısını hesaplar
     */
    protected function getSegmentCustomerCount(Segment $segment, $organizationId): int
    {
        if (!$segment->filters || !isset($segment->filters['conditions'])) {
            return 0;
        }

        $query = Customer::query();

        // Organization filtresi
        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        // User bazlı filtreleme (admin olmayan danışmanlar sadece kendi müşterilerini görsün)
        $user = auth()->user();
        $userRoleIds = $user->roles->pluck('id')->toArray();

        if (in_array(3, $userRoleIds) || in_array(7, $userRoleIds) && !in_array(1, $userRoleIds) && !in_array(2, $userRoleIds)) {
            $query->where('user_id', $user->id);
        }

        // Segment filtrelerini uygula
        $this->applyFiltersFromArray($query, $segment->filters);

        return $query->count();
    }

    /**
     * Array formatındaki filtreleri uygular
     */
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

    /**
     * Tek bir condition'ı query'ye uygular
     */
    protected function applyConditionToQuery($query, array $condition): void
    {
        $field = $condition['field'] ?? null;
        $operator = $condition['operator'] ?? null;
        $value = $condition['value'] ?? null;

        if (!$field || !$operator || $value === null) {
            return;
        }

        // Mock request oluştur
        $mockRequest = new Request();
        $mockRequest->merge([
            $field => $value,
            "{$field}_operator" => $operator
        ]);

        // Tarih filtreleri için özel işlem
        if (in_array($field, ['created_at', 'updated_at'])) {
            if ($operator === 'between' && is_array($value) && count($value) === 2) {
                $mockRequest->merge([
                    "{$field}_start" => $value[0],
                    "{$field}_end" => $value[1]
                ]);
            }
        }

        // Uygun filter metodunu çağır (trait'teki metodu kullan)
        $this->applyFilterByField($query, $mockRequest, $field);
    }
}
