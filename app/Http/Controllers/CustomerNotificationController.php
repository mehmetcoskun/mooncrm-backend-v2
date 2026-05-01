<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CustomerNotificationController extends Controller
{
    private const VALID_TYPES = [
        'user_notification',
        'group_notification',
        'sales_notification',
        'customer_message',
        'confirmation_email',
        'hotel_message',
        'hotel_email',
        'transfer_message',
    ];

    private const VARIANT_REQUIRED = ['hotel_message', 'hotel_email', 'transfer_message'];

    public function index(Request $request, Customer $customer)
    {
        if (Gate::none(['customer_NotificationAccess'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $authUser = auth()->user();
        $isDeveloper = $this->isDeveloper($authUser);
        $organizationId = $authUser->organization_id ?? $request->header('X-Organization-Id');

        if (!$isDeveloper && (int) $customer->organization_id !== (int) $organizationId) {
            return response()->json(['message' => 'Bu müşteriye erişim yetkiniz yok.'], 403);
        }

        $notifications = CustomerNotification::where('customer_id', $customer->id)
            ->with('triggeredByUser:id,name,email')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        if (!$isDeveloper) {
            $notifications = $notifications->map(function ($n) {
                $arr = $n->toArray();
                unset($arr['request'], $arr['response_body'], $arr['error']);
                return $arr;
            });
        }

        return response()->json(['data' => $notifications]);
    }

    public function trigger(Request $request, Customer $customer)
    {
        if (Gate::none(['customer_NotificationAccess'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $authUser = auth()->user();
        $organizationId = $authUser->organization_id ?? $request->header('X-Organization-Id');

        if (!$this->isDeveloper($authUser) && (int) $customer->organization_id !== (int) $organizationId) {
            return response()->json(['message' => 'Bu müşteriye erişim yetkiniz yok.'], 403);
        }

        $type = $request->input('type');
        $variant = $request->input('variant');

        if (!in_array($type, self::VALID_TYPES, true)) {
            return response()->json(['message' => 'Geçersiz bildirim tipi.'], 422);
        }

        if (in_array($type, self::VARIANT_REQUIRED, true) && !in_array($variant, ['reservation', 'cancel'], true)) {
            return response()->json(['message' => 'Bu bildirim tipi için variant (reservation|cancel) gerekli.'], 422);
        }

        $customer->load('organization', 'user', 'category', 'services', 'status');

        $context = [
            'triggered_by' => 'manual',
            'user_id' => $authUser->id,
        ];

        $controller = app(CustomerController::class);
        $reflection = new \ReflectionClass($controller);

        $method = match ($type) {
            'user_notification' => 'sendUserNotification',
            'group_notification' => 'sendGroupNotification',
            'sales_notification' => 'sendSalesNotification',
            'customer_message' => 'sendCustomerMessages',
            'confirmation_email' => 'sendConfirmationEmail',
            'hotel_message' => 'sendHotelMessage',
            'hotel_email' => 'sendHotelEmail',
            'transfer_message' => 'sendTransferMessage',
        };

        $reflectionMethod = $reflection->getMethod($method);
        $reflectionMethod->setAccessible(true);

        $statusId = $variant === 'reservation' ? 8 : 9;

        try {
            if (in_array($type, self::VARIANT_REQUIRED, true)) {
                $reflectionMethod->invoke($controller, $customer, $statusId, $context);
            } elseif ($type === 'customer_message') {
                $reflectionMethod->invoke($controller, $customer, $customer->category, null, $context);
            } else {
                $reflectionMethod->invoke($controller, $customer, $context);
            }
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Bildirim çağrısı sırasında hata oluştu.',
                'error' => $e->getMessage(),
            ], 500);
        }

        $notification = CustomerNotification::where('customer_id', $customer->id)
            ->where('type', $type)
            ->when($variant !== null, fn ($q) => $q->where('variant', $variant))
            ->orderByDesc('id')
            ->first();

        return response()->json([
            'message' => 'Bildirim tetiklendi.',
            'notification' => $notification,
        ]);
    }

    private function isDeveloper($user): bool
    {
        if (!$user) {
            return false;
        }
        return $user->id === 1 || $user->roles->contains('id', 1);
    }
}
