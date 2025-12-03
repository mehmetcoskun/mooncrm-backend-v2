<?php

namespace App\Traits;

use App\Models\Category;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait FilterableTrait
{
    protected function applyAdvancedFilters(Builder $query, Request $request): Builder
    {
        $logicalOperator = $request->input('logical_operator', 'and');

        if ($logicalOperator === 'or') {
            $query->where(function($q) use ($request) {
                $this->applySingleFilter($q, $request, 'categories', true);
                $this->applySingleFilter($q, $request, 'users', true);
                $this->applySingleFilter($q, $request, 'statuses', true);
                $this->applySingleFilter($q, $request, 'services', true);
                $this->applySingleFilter($q, $request, 'countries', true);
                $this->applySingleFilter($q, $request, 'trustpilot_review', true);
                $this->applySingleFilter($q, $request, 'google_maps_review', true);
                $this->applySingleFilter($q, $request, 'satisfaction_survey', true);
                $this->applySingleFilter($q, $request, 'warranty_sent', true);
                $this->applySingleFilter($q, $request, 'rpt', true);
                $this->applySingleFilter($q, $request, 'ad_name', true);
                $this->applySingleFilter($q, $request, 'adset_name', true);
                $this->applySingleFilter($q, $request, 'campaign_name', true);
                $this->applySingleFilter($q, $request, 'created_at', true);
                $this->applySingleFilter($q, $request, 'updated_at', true);
            });
        } else {
            $this->applyCategoryFilter($query, $request);
            $this->applyUserFilter($query, $request);
            $this->applyStatusFilter($query, $request);
            $this->applyServiceFilter($query, $request);
            $this->applyCountryFilter($query, $request);
            $this->applyBooleanFilter($query, $request, 'trustpilot_review');
            $this->applyBooleanFilter($query, $request, 'google_maps_review');
            $this->applyBooleanFilter($query, $request, 'satisfaction_survey');
            $this->applyBooleanFilter($query, $request, 'warranty_sent');
            $this->applyBooleanFilter($query, $request, 'rpt');
            $this->applyTextFilter($query, $request, 'ad_name');
            $this->applyTextFilter($query, $request, 'adset_name');
            $this->applyTextFilter($query, $request, 'campaign_name');
            $this->applyDateFilter($query, $request, 'created_at');
            $this->applyDateFilter($query, $request, 'updated_at');
        }

        return $query;
    }

    protected function applySingleFilter(Builder $query, Request $request, string $field, bool $useOr = false): void
    {
        $hasFilter = false;

        if ($field === 'categories' && $request->has('categories') && !empty($request->categories)) {
            $hasFilter = true;
        } elseif ($field === 'users' && $request->has('users') && !empty($request->users)) {
            $hasFilter = true;
        } elseif ($field === 'statuses' && $request->has('statuses') && !empty($request->statuses)) {
            $hasFilter = true;
        } elseif ($field === 'services' && $request->has('services') && !empty($request->services)) {
            $hasFilter = true;
        } elseif ($field === 'countries' && $request->has('countries') && !empty($request->countries)) {
            $hasFilter = true;
        } elseif ($request->has($field)) {
            $hasFilter = true;
        }

        if (!$hasFilter) {
            return;
        }

        if ($useOr) {
            $query->orWhere(function($subQuery) use ($request, $field) {
                $this->applyFilterByField($subQuery, $request, $field);
            });
        } else {
            $this->applyFilterByField($query, $request, $field);
        }
    }

    protected function applyFilterByField(Builder $query, Request $request, string $field): void
    {
        switch ($field) {
            case 'categories':
                $this->applyCategoryFilter($query, $request);
                break;
            case 'users':
                $this->applyUserFilter($query, $request);
                break;
            case 'statuses':
                $this->applyStatusFilter($query, $request);
                break;
            case 'services':
                $this->applyServiceFilter($query, $request);
                break;
            case 'countries':
                $this->applyCountryFilter($query, $request);
                break;
            case 'trustpilot_review':
            case 'google_maps_review':
            case 'satisfaction_survey':
            case 'warranty_sent':
            case 'rpt':
                $this->applyBooleanFilter($query, $request, $field);
                break;
            case 'ad_name':
            case 'adset_name':
            case 'campaign_name':
                $this->applyTextFilter($query, $request, $field);
                break;
            case 'created_at':
            case 'updated_at':
                $this->applyDateFilter($query, $request, $field);
                break;
        }
    }

    protected function applyCategoryFilter(Builder $query, Request $request): void
    {
        if ($request->has('categories') && !empty($request->categories)) {
            if (is_array($request->categories)) {
                $operator = $request->input('categories_operator', 'in');
                $allCategoryIds = [];

                foreach ($request->categories as $categoryId) {
                    $allCategoryIds[] = $categoryId;

                    $category = Category::find($categoryId);
                    if ($category) {
                        $childIds = [];
                        $this->collectChildCategoryIds($category, $childIds);
                        $allCategoryIds = array_merge($allCategoryIds, $childIds);
                    }
                }

                $allCategoryIds = array_unique($allCategoryIds);

                if ($operator === 'in') {
                    $query->whereIn('category_id', $allCategoryIds);
                } elseif ($operator === 'nin') {
                    $query->whereNotIn('category_id', $allCategoryIds);
                }
            }
        }
    }

    protected function applyUserFilter(Builder $query, Request $request): void
    {
        if ($request->has('users') && !empty($request->users)) {
            if (is_array($request->users)) {
                $operator = $request->input('users_operator', 'in');
                if ($operator === 'in') {
                    $query->whereIn('user_id', $request->users);
                } elseif ($operator === 'nin') {
                    $query->whereNotIn('user_id', $request->users);
                }
            }
        }
    }

    protected function applyStatusFilter(Builder $query, Request $request): void
    {
        if ($request->has('statuses') && !empty($request->statuses)) {
            if (is_array($request->statuses)) {
                $operator = $request->input('statuses_operator', 'in');
                if ($operator === 'in') {
                    $query->whereIn('status_id', $request->statuses);
                } elseif ($operator === 'nin') {
                    $query->whereNotIn('status_id', $request->statuses);
                }
            }
        }
    }

    protected function applyServiceFilter(Builder $query, Request $request): void
    {
        if ($request->has('services') && !empty($request->services)) {
            if (is_array($request->services)) {
                $operator = $request->input('services_operator', 'in');
                if ($operator === 'in') {
                    $query->whereHas('services', function ($q) use ($request) {
                        $q->whereIn('services.id', $request->services);
                    });
                } elseif ($operator === 'nin') {
                    $query->whereDoesntHave('services', function ($q) use ($request) {
                        $q->whereIn('services.id', $request->services);
                    });
                }
            }
        }
    }

    protected function applyCountryFilter(Builder $query, Request $request): void
    {
        if ($request->has('countries') && !empty($request->countries)) {
            if (is_array($request->countries)) {
                $operator = $request->input('countries_operator', 'in');
                if ($operator === 'in') {
                    $query->whereIn('country', $request->countries);
                } elseif ($operator === 'nin') {
                    $query->whereNotIn('country', $request->countries);
                }
            }
        }
    }

    protected function applyBooleanFilter(Builder $query, Request $request, string $field): void
    {
        if ($request->has($field)) {
            $operator = $request->input("{$field}_operator", 'eq');
            $value = $request->input($field);

            if ($operator === 'eq') {
                $salesInfoFields = [
                    'trustpilot_review',
                    'google_maps_review',
                    'satisfaction_survey',
                    'warranty_sent',
                    'rpt'
                ];

                if (in_array($field, $salesInfoFields)) {
                    $query->where("sales_info->{$field}", $value);
                } else {
                    $query->where($field, $value);
                }
            }
        }
    }

    protected function applyTextFilter(Builder $query, Request $request, string $field): void
    {
        if ($request->has($field) && !empty($request->{$field})) {
            $operator = $request->input("{$field}_operator", 'contains');
            $value = trim($request->input($field));

            if ($operator === 'contains') {
                $query->where($field, 'like', '%' . $value . '%');
            } elseif ($operator === 'eq') {
                $query->where($field, '=', $value);
            }
        }
    }

    protected function applyDateFilter(Builder $query, Request $request, string $field): void
    {
        if ($request->has("{$field}_operator")) {
            $operator = $request->input("{$field}_operator", 'eq');

            if ($operator === 'eq' && $request->has($field) && !empty($request->{$field})) {
                $date = Carbon::parse($request->{$field})->format('Y-m-d');
                $query->whereDate($field, '=', $date);
            } elseif ($operator === 'between') {
                $startDate = $request->input("{$field}_start");
                $endDate = $request->input("{$field}_end");

                if (!empty($startDate) && !empty($endDate)) {
                    $start = Carbon::parse($startDate)->format('Y-m-d');
                    $end = Carbon::parse($endDate)->format('Y-m-d');
                    $query->whereBetween($field, [$start, $end]);
                }
            }
        }
    }

    protected function collectChildCategoryIds($category, &$childIds): void
    {
        foreach ($category->children as $child) {
            $childIds[] = $child->id;
            $this->collectChildCategoryIds($child, $childIds);
        }
    }
}

