<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['user_Access', 'customer_Access', 'statistic_Access', 'report_Access', 'tag_Access', 'segment_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if ($organizationId) {
            $users = User::where('organization_id', $organizationId);
        } else {
            $users = User::where('organization_id', null);
        }

        return $users->orderBy('id', 'desc')->get()->load('organization', 'roles.permissions');
    }

    public function store(Request $request)
    {
        if (Gate::none(['user_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        $data['organization_id'] = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if (!empty($data['email'])) {
            $existingUser = User::where('email', $data['email'])
                ->first();

            if ($existingUser) {
                return response()->json([
                    'message' => 'Bu e-posta adresi ile kayıtlı bir kullanıcı zaten mevcut.',
                ], 422);
            }
        }

        $user = User::create($data);
        $roleIds = collect($request->roles)->pluck('id');
        $user->roles()->attach($roleIds);

        return $user->load('organization', 'roles.permissions');
    }

    public function update(Request $request, User $user)
    {
        if (Gate::none(['user_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $data = $request->all();

        if (!empty($data['email'])) {
            $existingUser = User::where('email', $data['email'])
                ->where('id', '!=', $user->id)
                ->first();

            if ($existingUser) {
                return response()->json([
                    'message' => 'Bu e-posta adresi ile kayıtlı bir kullanıcı zaten mevcut.',
                ], 422);
            }
        }

        $user->update($data);

        if ($request->has('roles') && is_array($request->roles) && count($request->roles) > 0) {
            $roleIds = collect($request->roles)->pluck('id');
            $user->roles()->sync($roleIds);
        }
        
        $user->load('organization', 'roles.permissions');

        return response()->json($user);
    }

    public function destroy(User $user)
    {
        if (Gate::none(['user_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Kendinizi silemezsiniz!'], 403);
        }

        if ($user->id === 1) {
            return response()->json(['message' => 'Bu kullanıcı silinemez!'], 403);
        }

        return $user->delete();
    }

    public function tokens(Request $request, User $user)
    {
        if (Gate::none(['user_ApiKeyAccess', 'user_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        return $user->tokens;
    }

    public function createToken(Request $request, User $user)
    {
        if (Gate::none(['user_ApiKeyCreate']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $token = $user->createToken($request->name, ['*'], null)->plainTextToken;

        return response()->json([
            'token' => $token
        ]);
    }

    public function destroyToken(Request $request, User $user, $token)
    {
        if (Gate::none(['user_ApiKeyDelete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $user->tokens()->where('id', $token)->delete();

        return response()->json([
            'message' => 'Token başarıyla iptal edildi!'
        ]);
    }
}
