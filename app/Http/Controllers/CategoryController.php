<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['category_Access', 'customer_Access', 'statistic_Access', 'report_Access', 'tag_Access', 'segment_Access', 'web_form_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $categories = Category::where(function ($query) use ($organizationId) {
            $query->where('organization_id', $organizationId)
                ->orWhere(function ($query) {
                    $query->whereNull('organization_id')
                        ->where('is_global', 1);
                });
        });

        return $categories->orderBy('id', 'desc')
            ->get()
            ->load('organization', 'parent', 'children');
    }

    public function store(Request $request)
    {
        if (Gate::none(['category_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        if (!$request->is_global) {
            $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');
        }

        if (isset($data['lead_form_id']) && $data['lead_form_id']) {
            $organizationId = $data['organization_id'] ?? null;
            $existingCategory = Category::where('lead_form_id', $data['lead_form_id'])
                ->where(function ($query) use ($organizationId) {
                    $query->where('organization_id', $organizationId)
                        ->orWhere(function ($query) {
                            $query->whereNull('organization_id')
                                ->where('is_global', 1);
                        });
                })->first();

            if ($existingCategory) {
                return response()->json([
                    'message' => 'Bu lead form ID ile kayıtlı bir kategori zaten mevcut.',
                ], 422);
            }
        }

        return Category::create($data)->load('organization', 'parent', 'children');
    }

    public function update(Request $request, Category $category)
    {
        if (Gate::none(['category_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        if (!$request->is_global) {
            $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');
        }

        if (isset($data['lead_form_id']) && $data['lead_form_id']) {
            $organizationId = $data['organization_id'] ?? null;
            $existingCategory = Category::where('lead_form_id', $data['lead_form_id'])
                ->where('id', '!=', $category->id)
                ->where(function ($query) use ($organizationId) {
                    $query->where('organization_id', $organizationId)
                        ->orWhere(function ($query) {
                            $query->whereNull('organization_id')
                                ->where('is_global', 1);
                        });
                })->first();

            if ($existingCategory) {
                return response()->json([
                    'message' => 'Bu lead form ID ile kayıtlı başka bir kategori zaten mevcut.',
                ], 422);
            }
        }

        $category->update($data);
        $category->load('organization', 'parent', 'children');

        return response()->json($category);
    }

    public function destroy(Category $category)
    {
        if (Gate::none(['category_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        if ($category->parent_id) {
            $category->customers()->update(['category_id' => $category->parent_id]);
        }

        return $category->delete();
    }
}
