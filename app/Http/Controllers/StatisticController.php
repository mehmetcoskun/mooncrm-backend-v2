<?php

namespace App\Http\Controllers;

use App\Traits\FilterableTrait;
use App\Models\Category;
use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;

class StatisticController extends Controller
{
    use FilterableTrait;
    public function getStatistics(Request $request)
    {
        $organizationId = auth()->user()->organization_id ?? request()->header('X-Organization-Id');

        $selectedCategoryIds = [];
        $selectedCategoriesWithAncestors = [];
        $excludeCategoryIds = [];
        
        if ($request->has('categories') && !empty($request->categories)) {
            $selectedCategoryIds = is_array($request->categories) ? $request->categories : [$request->categories];
            
            if ($request->has('categories_operator') && $request->categories_operator === 'nin') {
                $excludedCategories = Category::whereIn('id', $selectedCategoryIds)
                    ->where(function ($query) use ($organizationId) {
                        $query->where('organization_id', $organizationId)
                            ->orWhere('is_global', true);
                    })
                    ->get();
                
                foreach ($excludedCategories as $category) {
                    $excludeCategoryIds[] = $category->id;
                    $childIds = [];
                    $this->collectChildCategoryIds($category, $childIds);
                    $excludeCategoryIds = array_merge($excludeCategoryIds, $childIds);
                }
                
                $selectedCategoryIds = [];
            } else {
                $selectedCategories = Category::whereIn('id', $selectedCategoryIds)
                    ->where(function ($query) use ($organizationId) {
                        $query->where('organization_id', $organizationId)
                            ->orWhere('is_global', true);
                    })
                    ->get();
                    
                foreach ($selectedCategories as $category) {
                    $currentCat = $category;
                    $categoryAncestors = [$currentCat->id];
                    
                    while ($currentCat->parent_id !== null) {
                        $currentCat = Category::find($currentCat->parent_id);
                        if ($currentCat) {
                            $categoryAncestors[] = $currentCat->id;
                        } else {
                            break;
                        }
                    }
                    
                    $selectedCategoriesWithAncestors = array_merge($selectedCategoriesWithAncestors, $categoryAncestors);
                }
                
                $selectedCategoriesWithAncestors = array_unique($selectedCategoriesWithAncestors);
            }
        }

        $categoriesQuery = Category::with([
            'children' => function ($query) use ($organizationId, $selectedCategoriesWithAncestors, $selectedCategoryIds, $excludeCategoryIds) {
                $query->where(function ($q) use ($organizationId) {
                    $q->where('organization_id', $organizationId)
                        ->orWhere('is_global', true);
                });
                
                if (!empty($selectedCategoriesWithAncestors)) {
                    $query->whereIn('id', $selectedCategoriesWithAncestors);
                }
                
                if (!empty($excludeCategoryIds)) {
                    $query->whereNotIn('id', $excludeCategoryIds);
                }
            }
        ])
        ->whereNull('parent_id')
        ->where(function ($query) use ($organizationId) {
            $query->where('organization_id', $organizationId)
                ->orWhere('is_global', true);
        });
        
        if (!empty($selectedCategoriesWithAncestors)) {
            $categoriesQuery->whereIn('id', $selectedCategoriesWithAncestors);
        }
        
        if (!empty($excludeCategoryIds)) {
            $categoriesQuery->whereNotIn('id', $excludeCategoryIds);
        }

        $categories = $categoriesQuery->get();
        
        $customersQuery = Customer::where('organization_id', $organizationId);

        // Category filtresi hariç tüm filtreleri uygula (category özel olarak işleniyor)
        $tempRequest = clone $request;
        $tempRequest->merge(['categories' => null, 'categories_operator' => null]);
        $this->applyAdvancedFilters($customersQuery, $tempRequest);

        $totalCustomers = $customersQuery->count();

        $statistics = [];

        foreach ($categories as $category) {
            $statistics[] = $this->calculateCategoryStatistics(
                $category, 
                $totalCustomers, 
                $organizationId, 
                $request->all(), 
                $selectedCategoryIds,
                $excludeCategoryIds
            );
        }

        return response()->json($statistics);
    }

    private function calculateCategoryStatistics($category, $totalCustomers, $organizationId, $filters = [], $selectedCategoryIds = [], $excludeCategoryIds = [])
    {
        $categoryIds = [$category->id];
        $this->collectChildCategoryIds($category, $categoryIds);
        
        if (!empty($selectedCategoryIds)) {
            $includeOnlyTheseCategoryIds = [];

            if (in_array($category->id, $selectedCategoryIds)) {
                $includeOnlyTheseCategoryIds = $categoryIds;
            } else {
                foreach ($categoryIds as $catId) {
                    if (in_array($catId, $selectedCategoryIds)) {
                        $includeOnlyTheseCategoryIds[] = $catId;
                    }
                }
            }
            
            if (empty($includeOnlyTheseCategoryIds) && !empty(array_intersect($categoryIds, $selectedCategoryIds))) {
                $includeOnlyTheseCategoryIds = array_intersect($categoryIds, $selectedCategoryIds);
            }
            
            if (!empty($includeOnlyTheseCategoryIds)) {
                $categoryIds = $includeOnlyTheseCategoryIds;
            }
        }

        if (!empty($excludeCategoryIds)) {
            $categoryIds = array_diff($categoryIds, $excludeCategoryIds);
            
            if (empty($categoryIds)) {
                return [
                    'id' => $category->id,
                    'type' => $category->title,
                    'contacts' => ['total' => 0, 'percentage' => 0],
                    'offers' => ['total' => 0, 'percentage' => 0],
                    'sales' => ['total' => 0, 'percentage' => 0],
                    'canceled' => ['total' => 0, 'percentage' => 0],
                    'children' => []
                ];
            }
        }

        $customersQuery = Customer::whereIn('category_id', $categoryIds)
            ->where('organization_id', $organizationId);

        // Category filtresi hariç tüm filtreleri uygula
        $tempFilters = $filters;
        unset($tempFilters['categories']);
        unset($tempFilters['categories_operator']);
        
        $request = new Request($tempFilters);
        $this->applyAdvancedFilters($customersQuery, $request);

        $contacts = $customersQuery->count();

        $offers = (clone $customersQuery)
            ->whereHas('services')
            ->count();

        $sales = (clone $customersQuery)
            ->where('status_id', 8)
            ->count();

        $canceledSales = (clone $customersQuery)
            ->where('status_id', 9)
            ->count();

        $offerPercentage = $contacts > 0 ? round(($offers / $contacts) * 100, 2) : 0;
        $salePercentage = $offers > 0 ? round(($sales / $offers) * 100, 2) : 0;
        $cancelPercentage = ($sales + $canceledSales) > 0 ? round(($canceledSales / ($sales + $canceledSales)) * 100, 2) : 0;

        $stats = [
            'id' => $category->id,
            'type' => $category->title,
            'contacts' => [
                'total' => $contacts,
                'percentage' => $totalCustomers > 0 ? round(($contacts / $totalCustomers) * 100, 2) : 0
            ],
            'offers' => [
                'total' => $offers,
                'percentage' => $offerPercentage
            ],
            'sales' => [
                'total' => $sales,
                'percentage' => $salePercentage
            ],
            'canceled' => [
                'total' => $canceledSales,
                'percentage' => $cancelPercentage
            ],
            'children' => []
        ];

        $filteredChildren = $category->children;
        
        if (!empty($selectedCategoryIds)) {
            $filteredChildren = $category->children->filter(function ($child) use ($selectedCategoryIds) {
                return in_array($child->id, $selectedCategoryIds) || 
                       $this->isAncestorOfSelectedCategories($child, $selectedCategoryIds);
            });
        }
        
        if (!empty($excludeCategoryIds)) {
            $filteredChildren = $category->children->filter(function ($child) use ($excludeCategoryIds) {
                return !in_array($child->id, $excludeCategoryIds);
            });
        }

        foreach ($filteredChildren as $child) {
            $childStats = $this->calculateCategoryStatistics(
                $child,
                $totalCustomers,
                $organizationId,
                $filters,
                $selectedCategoryIds,
                $excludeCategoryIds
            );
            
            $stats['children'][] = $childStats;
        }

        return $stats;
    }

    private function isAncestorOfSelectedCategories($category, $selectedCategoryIds)
    {
        if (in_array($category->id, $selectedCategoryIds)) {
            return true;
        }
        
        foreach ($category->children as $child) {
            if (in_array($child->id, $selectedCategoryIds) || $this->isAncestorOfSelectedCategories($child, $selectedCategoryIds)) {
                return true;
            }
        }
        
        return false;
    }
}
