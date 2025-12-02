<?php

namespace App\Http\Controllers;

use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorAuthController extends Controller
{
    protected $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    public function generateQrCode(Request $request)
    {
        $user = $request->user();

        if (!$user->two_factor_secret) {
            $secret = $this->google2fa->generateSecretKey();
            $user->two_factor_secret = encrypt($secret);
            $user->save();
        } else {
            $secret = decrypt($user->two_factor_secret);
        }

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);
        $qrCodeSvg = $writer->writeString($qrCodeUrl);

        return response()->json([
            'qr_code' => $qrCodeSvg,
            'secret' => $secret,
        ]);
    }

    public function enable(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return response()->json([
                'message' => 'Lütfen önce QR kodu oluşturun!'
            ], 422);
        }

        $secret = decrypt($user->two_factor_secret);
        $valid = $this->google2fa->verifyKey($secret, $request->code);

        if (!$valid) {
            return response()->json([
                'message' => 'Geçersiz doğrulama kodu!'
            ], 422);
        }

        $recoveryCodes = $user->generateRecoveryCodes();

        $user->two_factor_enabled = true;
        $user->two_factor_confirmed_at = now();
        $user->save();

        return response()->json([
            'message' => 'İki faktörlü kimlik doğrulama başarıyla etkinleştirildi!',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function disable(Request $request)
    {
        $request->validate([
            'password' => 'required',
        ]);

        $user = $request->user();

        if (!password_verify($request->password, $user->password)) {
            return response()->json([
                'message' => 'Şifreniz yanlış!'
            ], 422);
        }

        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_enabled = false;
        $user->two_factor_confirmed_at = null;
        $user->save();

        return response()->json([
            'message' => 'İki faktörlü kimlik doğrulama başarıyla devre dışı bırakıldı!',
        ]);
    }

    public function verify(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'code' => 'required|string',
        ]);

        $user = \App\Models\User::find($request->user_id);

        if (!$user->two_factor_enabled) {
            return response()->json([
                'message' => 'İki faktörlü kimlik doğrulama etkin değil!'
            ], 422);
        }

        if (strlen($request->code) === 10) {
            if ($user->validateRecoveryCode($request->code)) {
                $token = $user->createToken('crm-login', ['*'], now()->addHours(24))->plainTextToken;
                
                return response()->json([
                    'message' => 'Kurtarma kodu ile giriş başarılı!',
                    'token' => $token,
                    'user' => $user->load('organization', 'roles.permissions'),
                    'organization_id' => $user->organization_id,
                    'needs_password_change' => $user->needsPasswordChange(),
                ]);
            }
        }

        $secret = decrypt($user->two_factor_secret);
        $valid = $this->google2fa->verifyKey($secret, $request->code, 2);

        if (!$valid) {
            return response()->json([
                'message' => 'Geçersiz doğrulama kodu!'
            ], 422);
        }

        $token = $user->createToken('crm-login', ['*'], now()->addHours(24))->plainTextToken;

        return response()->json([
            'message' => 'İki faktörlü doğrulama başarılı!',
            'token' => $token,
            'user' => $user->load('organization', 'roles.permissions'),
            'organization_id' => $user->organization_id,
            'needs_password_change' => $user->needsPasswordChange(),
        ]);
    }

    public function status(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->two_factor_enabled,
            'confirmed_at' => $user->two_factor_confirmed_at,
        ]);
    }

    public function regenerateRecoveryCodes(Request $request)
    {
        $request->validate([
            'password' => 'required',
        ]);

        $user = $request->user();

        if (!password_verify($request->password, $user->password)) {
            return response()->json([
                'message' => 'Şifreniz yanlış!'
            ], 422);
        }

        if (!$user->two_factor_enabled) {
            return response()->json([
                'message' => 'İki faktörlü kimlik doğrulama etkin değil!'
            ], 422);
        }

        $recoveryCodes = $user->generateRecoveryCodes();

        return response()->json([
            'message' => 'Kurtarma kodları yenilendi!',
            'recovery_codes' => $recoveryCodes,
        ]);
    }
}

