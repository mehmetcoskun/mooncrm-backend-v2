<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TagController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['tag_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $tags = Tag::where('organization_id', $organizationId);

        return $tags->orderBy('id', 'desc')->get()->load('organization', 'categories', 'users');
    }

    public function store(Request $request)
    {
        if (Gate::none(['tag_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $tag = Tag::create($data);

        if ($request->has('users')) {
            $userIds = collect($request->users)->pluck('id');
            $tag->users()->attach($userIds);
        }

        if ($request->has('categories')) {
            $categoryIds = collect($request->categories)->pluck('id');
            $tag->categories()->attach($categoryIds);
        }

        return $tag->load('organization', 'categories', 'users');
    }

    public function update(Request $request, Tag $tag)
    {
        if (Gate::none(['tag_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $tag->update($data);

        if ($request->has('users')) {
            $userIds = collect($request->users)->pluck('id');
            $tag->users()->sync($userIds);
        }

        if ($request->has('categories')) {
            $categoryIds = collect($request->categories)->pluck('id');
            $tag->categories()->sync($categoryIds);
        }

        $tag->load('organization', 'categories', 'users');

        return response()->json($tag);
    }

    public function destroy(Tag $tag)
    {
        if (Gate::none(['tag_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $tag->delete();
    }
}
