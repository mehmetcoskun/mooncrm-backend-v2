<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response([
                'message' => 'Lütfen bilgilerinizi kontrol edin!'
            ], 500);
        }

        $isDeveloper = $user->id === 1 || $user->roles->contains('id', 1);

        if (!$isDeveloper && !$request->organization_code) {
            return response([
                'message' => 'Firma kodu boş olamaz!'
            ], 500);
        }

        if ($user->is_active == false) {
            return response([
                'message' => 'Hesabınız şu anda aktif değil. Lütfen sistem yöneticiniz ile iletişime geçiniz.'
            ], 500);
        }

        $organization = null;
        if ($request->organization_code) {
            $organization = Organization::where('code', $request->organization_code)->first();
            if (!$organization) {
                return response([
                    'message' => 'Firma bulunamadı!'
                ], 500);
            }

            if (!$organization->is_active) {
                return response([
                    'message' => 'Firma hesabı aktif değil. Lütfen sistem yöneticiniz ile iletişime geçiniz.'
                ], 500);
            }
        }

        if (!$isDeveloper) {
            if ((int) $user->organization_id !== (int) $organization->id) {
                return response([
                    'message' => 'Lütfen bilgilerinizi kontrol edin!'
                ], 500);
            }
        }

        PersonalAccessToken::where('name', 'crm-login')
            ->where('expires_at', '<', now())
            ->delete();

        if ($user->two_factor_enabled) {
            return response()->json([
                'requires_two_factor' => true,
                'user_id' => $user->id,
            ]);
        }

        return response()->json([
            'user' => $user->load('organization', 'roles.permissions'),
            'organization_id' => $organization ? $organization->id : null,
            'token' => $user->createToken('crm-login', ['*'], now()->addHours(24))->plainTextToken,
            'needs_password_change' => $user->needsPasswordChange(),
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('organization', 'roles.permissions');
        $user->needs_password_change = $user->needsPasswordChange();
        return $user;
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'Mevcut şifreniz yanlış!'
            ], 422);
        }

        $user->password = $request->new_password;
        $user->save();

        return response()->json([
            'message' => 'Şifreniz başarıyla değiştirildi.'
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();
        
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

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        $user->load('organization', 'roles.permissions');

        return response()->json($user);
    }
}
