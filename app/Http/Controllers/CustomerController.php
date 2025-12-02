<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerLog;
use App\Models\Category;
use App\Models\Organization;
use App\Models\PartnerHotel;
use App\Models\PartnerTransfer;
use App\Models\Segment;
use App\Models\Setting;
use App\Models\Tag;
use App\Models\User;
use App\Models\WhatsappSession;
use App\Traits\FilterableTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twilio\Rest\Client;

class CustomerController extends Controller
{
    use FilterableTrait;

    public function index(Request $request)
    {
        if (Gate::none(['customer_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $first = $request->get('first', 0);
        $rows = $request->get('rows', 10);
        $search = $request->get('search', '');

        $user = auth()->user();
        $userRoles = $user->roles;
        $userRoleIds = $userRoles->pluck('id')->toArray();
        $allowedStatusIds = [];

        foreach ($userRoles as $role) {
            $roleStatuses = $role->statuses;
            if ($roleStatuses->isEmpty()) {
                $allowedStatusIds = null;
                break;
            }
            $allowedStatusIds = array_merge($allowedStatusIds, $roleStatuses->pluck('id')->toArray());
        }

        if ($allowedStatusIds !== null) {
            $allowedStatusIds = array_unique($allowedStatusIds);
        }

        $customers = Customer::where('organization_id', $organizationId);

        if (in_array(3, $userRoleIds) || in_array(7, $userRoleIds) && !in_array(1, $userRoleIds) && !in_array(2, $userRoleIds)) {
            $customers->where('user_id', $user->id);
        }

        if ($allowedStatusIds !== null) {
            $customers->whereIn('status_id', $allowedStatusIds);
        }

        $this->applyAdvancedFilters($customers, $request);

        if ($search) {
            $customers->where(function ($query) use ($search) {
                $query->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $totalRecords = $customers->count();

        $customers = $customers->orderBy('created_at', 'desc')
            ->offset($first)
            ->limit($rows)
            ->get()
            ->load('organization', 'user', 'category', 'services', 'status');

        return response()->json([
            'data' => $customers,
            'totalRecords' => $totalRecords,
        ]);
    }

    public function store(Request $request)
    {
        if (Gate::none(['customer_Create']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');
        $organization = Organization::find($organizationId);

        if ($organization) {
            $now = Carbon::now();

            if ($organization->trial_ends_at) {
                $trialEnd = Carbon::parse($organization->trial_ends_at);
                if ($now->greaterThan($trialEnd)) {
                    return response()->json([
                        'message' => 'Deneme süreniz sona ermiştir. Müşteri ekleyemezsiniz.',
                        'expired' => true
                    ], 403);
                }
            }

            if ($organization->license_ends_at) {
                $licenseEnd = Carbon::parse($organization->license_ends_at);
                if ($now->greaterThan($licenseEnd)) {
                    return response()->json([
                        'message' => 'Lisans süreniz sona ermiştir. Müşteri ekleyemezsiniz.',
                        'expired' => true
                    ], 403);
                }
            }
        }

        $data = $request->all();

        $data['organization_id'] = $organizationId;
        $data['user_id'] = $data['user_id'] ?? auth()->user()->id;

        if (!empty($data['email'])) {
            $existingCustomer = Customer::where('organization_id', $data['organization_id'])
                ->where('email', $data['email'])
                ->first();

            if ($existingCustomer) {
                return response()->json([
                    'message' => 'Bu e-posta adresi ile kayıtlı bir müşteri zaten mevcut.',
                ], 422);
            }
        }

        if (!empty($data['phone'])) {
            $existingCustomer = Customer::where('organization_id', $data['organization_id'])
                ->where('phone', $data['phone'])
                ->first();

            if ($existingCustomer) {
                return response()->json([
                    'message' => 'Bu telefon numarası ile kayıtlı bir müşteri zaten mevcut.',
                ], 422);
            }
        }

        $customer = Customer::create($data);

        if (isset($data['service_ids']) && is_array($data['service_ids'])) {
            $customer->services()->sync($data['service_ids']);
        }

        return $customer->load('organization', 'user', 'category', 'services', 'status');
    }

    public function show(Request $request, Customer $customer)
    {
        if (Gate::none(['customer_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if ((int) $customer->organization_id !== (int) $organizationId) {
            return response()->json(['message' => 'Bu müşteriye erişim yetkiniz yok.'], 403);
        }

        return response()->json($customer->load('organization', 'user', 'category', 'services', 'status'));
    }

    public function update(Request $request, Customer $customer)
    {
        if (Gate::none(['customer_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if ((int) $customer->organization_id !== (int) $organizationId) {
            return response()->json(['message' => 'Bu müşteriye erişim yetkiniz yok.'], 403);
        }

        $organization = Organization::find($customer->organization_id);

        if ($organization) {
            $now = Carbon::now();

            if ($organization->trial_ends_at) {
                $trialEnd = Carbon::parse($organization->trial_ends_at);
                if ($now->greaterThan($trialEnd)) {
                    return response()->json([
                        'message' => 'Deneme süreniz sona ermiştir. Müşteri düzenleyemezsiniz.',
                        'expired' => true
                    ], 403);
                }
            }

            if ($organization->license_ends_at) {
                $licenseEnd = Carbon::parse($organization->license_ends_at);
                if ($now->greaterThan($licenseEnd)) {
                    return response()->json([
                        'message' => 'Lisans süreniz sona ermiştir. Müşteri düzenleyemezsiniz.',
                        'expired' => true
                    ], 403);
                }
            }
        }

        $data = $request->all();

        if (!empty($data['email']) && $data['email'] !== $customer->email) {
            $existingCustomer = Customer::where('organization_id', $customer->organization_id)
                ->where('email', $data['email'])
                ->where('id', '!=', $customer->id)
                ->first();

            if ($existingCustomer) {
                return response()->json([
                    'message' => 'Bu e-posta adresi ile kayıtlı bir müşteri zaten mevcut.',
                ], 422);
            }
        }

        if (!empty($data['status_id']) && in_array($data['status_id'], [5, 6, 7, 8]) &&
            (empty($data['service_ids']) || !is_array($data['service_ids']) || count($data['service_ids']) === 0) &&
            $customer->services()->count() === 0) {
            return response()->json([
                'message' => 'Hizmet seçilmediği için durum değiştirilemez.',
            ], 422);
        }

        $oldStatusId = $customer->status_id;
        $oldTravelInfo = $customer->travel_info;

        $customer->update($data);

        if (isset($data['service_ids']) && is_array($data['service_ids'])) {
            $customer->services()->sync($data['service_ids']);
        }

        $customer->load('organization', 'user', 'category', 'services', 'status');

        $lastTravelInfo = $customer->travel_info && is_array($customer->travel_info) && count($customer->travel_info) > 0 ?
            $customer->travel_info[count($customer->travel_info) - 1] : null;

        if ($lastTravelInfo) {
            if (isset($data['status_id']) && $data['status_id'] == 9) {
                if (!$lastTravelInfo['is_custom_hotel'] && isset($lastTravelInfo['partner_hotel_id']) && !empty($lastTravelInfo['partner_hotel_id'])) {
                    $this->sendHotelMessage($customer, statusId: 9);
                    $this->sendHotelEmail($customer, 9);
                }

                if (isset($lastTravelInfo['partner_transfer_id']) && !empty($lastTravelInfo['partner_transfer_id'])) {
                    $this->sendTransferMessage($customer, 9);
                }
            } else if (isset($data['status_id']) && $data['status_id'] == 8) {
                $oldLastTravelInfo = $oldTravelInfo && is_array($oldTravelInfo) && count($oldTravelInfo) > 0 ?
                    $oldTravelInfo[count($oldTravelInfo) - 1] : null;
                $oldRoomType = $oldLastTravelInfo && isset($oldLastTravelInfo['room_type']) ? $oldLastTravelInfo['room_type'] : '';

                $newRoomType = $lastTravelInfo && isset($lastTravelInfo['room_type']) ? $lastTravelInfo['room_type'] : '';
                $hasTravelInfo = !empty($customer->travel_info) && isset($lastTravelInfo['arrival_date']) && isset($lastTravelInfo['departure_date']);

                if ($hasTravelInfo && !empty($newRoomType) && (($oldRoomType != $newRoomType) || ($oldTravelInfo != $customer->travel_info) || ($oldStatusId != $data['status_id']))) {
                    $this->sendConfirmationEmail($customer);
                    $this->sendSalesNotification($customer);
                    if (!$lastTravelInfo['is_custom_hotel'] && isset($lastTravelInfo['partner_hotel_id']) && !empty($lastTravelInfo['partner_hotel_id'])) {
                        $this->sendHotelMessage($customer, 8);
                        $this->sendHotelEmail($customer, 8);
                    }

                    if (isset($lastTravelInfo['partner_transfer_id']) && !empty($lastTravelInfo['partner_transfer_id'])) {
                        $this->sendTransferMessage($customer, 8);
                    }
                }
            }
        }

        return response()->json($customer);
    }

    public function destroy(Request $request, Customer $customer)
    {
        if (Gate::none(['customer_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if ((int) $customer->organization_id !== (int) $organizationId) {
            return response()->json(['message' => 'Bu müşteriye erişim yetkiniz yok.'], 403);
        }

        return $customer->delete();
    }

    public function bulkDelete(Request $request)
    {
        if (Gate::none(['customer_Delete']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $ids = $request->input('ids', []);
        
        if (empty($ids) || !is_array($ids)) {
            return response()->json(['message' => 'Geçersiz ID listesi'], 400);
        }

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $deleted = Customer::where('organization_id', $organizationId)
            ->whereIn('id', $ids)
            ->delete();

        return response()->json([
            'message' => 'Müşteriler başarıyla silindi',
            'deleted_count' => $deleted
        ]);
    }

    public function bulkUpdateStatus(Request $request)
    {
        if (Gate::none(['customer_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $ids = $request->input('ids', []);
        $statusId = $request->input('status_id');
        
        if (empty($ids) || !is_array($ids) || !$statusId) {
            return response()->json(['message' => 'Geçersiz parametreler'], 400);
        }

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $updated = Customer::where('organization_id', $organizationId)
            ->whereIn('id', $ids)
            ->update(['status_id' => $statusId]);

        return response()->json([
            'message' => 'Müşteri durumları başarıyla güncellendi',
            'updated_count' => $updated
        ]);
    }

    public function bulkUpdateCategory(Request $request)
    {
        if (Gate::none(['customer_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $ids = $request->input('ids', []);
        $categoryId = $request->input('category_id');
        
        if (empty($ids) || !is_array($ids) || !$categoryId) {
            return response()->json(['message' => 'Geçersiz parametreler'], 400);
        }

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $updated = Customer::where('organization_id', $organizationId)
            ->whereIn('id', $ids)
            ->update(['category_id' => $categoryId]);

        return response()->json([
            'message' => 'Müşteri kategorileri başarıyla güncellendi',
            'updated_count' => $updated
        ]);
    }

    public function bulkUpdateUser(Request $request)
    {
        if (Gate::none(['customer_Edit']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $ids = $request->input('ids', []);
        $userId = $request->input('user_id');
        
        if (empty($ids) || !is_array($ids) || !$userId) {
            return response()->json(['message' => 'Geçersiz parametreler'], 400);
        }

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $updated = Customer::where('organization_id', $organizationId)
            ->whereIn('id', $ids)
            ->update(['user_id' => $userId]);

        return response()->json([
            'message' => 'Müşteri danışmanları başarıyla güncellendi',
            'updated_count' => $updated
        ]);
    }

    public function segmentFilter(Request $request, Segment $segment)
    {
        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if ((int) $segment->organization_id !== (int) $organizationId) {
            return response()->json(['message' => 'Bu segmente erişim yetkiniz yok.'], 403);
        }

        if (!$segment->filters || !isset($segment->filters['conditions'])) {
            return response()->json([]);
        }

        $query = Customer::with(['category', 'user', 'status', 'services']);

        if ($organizationId) {
            $query->where('organization_id', $organizationId);
        }

        $this->applyFiltersFromArray($query, $segment->filters);

        $customers = $query->get();

        return response()->json($customers);
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

    public function logs(Request $request, Customer $customer)
    {
        if (Gate::none(['customer_LogAccess']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        if ((int) $customer->organization_id !== (int) $organizationId) {
            return response()->json(['message' => 'Bu müşteriye erişim yetkiniz yok.'], 403);
        }

        $logs = CustomerLog::with(['user'])
            ->where('customer_id', $customer->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($logs);
    }

    public function webhook(Request $request)
    {
        $data = $request->all();
        $organizationId = auth()->user()->organization_id;
        $data['organization_id'] = $organizationId;

        if ($request->input('lead_form_id')) {
            $category = Category::where('organization_id', $organizationId)->where('lead_form_id', $request->input('lead_form_id'))->first();
            if ($category) {
                $data['category_id'] = $category->id;
            }
        }

        return $this->handleCustomerEntry($data);
    }

    public function handleCustomerEntry($data)
    {
        $organizationId = $data['organization_id'];

        if (!empty($data['phone'])) {
            try {
                $phoneUtil = PhoneNumberUtil::getInstance();
                $phoneNumber = $phoneUtil->parse($data['phone']);
                $data['country'] = $phoneUtil->getRegionCodeForNumber($phoneNumber);
            } catch (\Exception $e) {
            }
        }

        $existingCustomer = null;
        if (!empty($data['email'])) {
            $existingCustomer = Customer::where('organization_id', $organizationId)
                ->where('email', $data['email'])
                ->first();
        }
        if (!$existingCustomer && !empty($data['phone'])) {
            $existingCustomer = Customer::where('organization_id', $organizationId)
                ->where('phone', $data['phone'])
                ->first();
        }

        if ($existingCustomer) {
            if ($existingCustomer->status_id === 11) {
                return response()->json([
                    'message' => 'Bu müşteri engellenmiştir.',
                ], 200);
            }

            $existingCustomer->duplicate_count = ($existingCustomer->duplicate_count ?? 0) + 1;
            $existingCustomer->created_at = now();
            $existingCustomer->save();

            if ($existingCustomer->user_id) {
                $this->sendCustomerMessages($existingCustomer);
                $this->sendUserNotification($existingCustomer);
            }

            return $existingCustomer->load('organization', 'user', 'category', 'services', 'status');
        }

        $category = null;
        $tag = null;
        $customerLanguage = null;

        if (!empty($data['phone'])) {
            try {
                $phoneUtil = PhoneNumberUtil::getInstance();
                $phoneNumber = $phoneUtil->parse($data['phone']);
                $customerLanguage = strtolower($phoneUtil->getRegionCodeForNumber($phoneNumber));
            } catch (\Exception $e) {
            }
        }

        if (!empty($data['category_id'])) {
            $category = Category::find($data['category_id']);
            if ($category) {
                $categoryIds = [$category->id];
                $parentCategory = $category;
                while ($parentCategory->parent_id) {
                    $parentCategory = Category::find($parentCategory->parent_id);
                    if ($parentCategory) {
                        $categoryIds[] = $parentCategory->id;
                    }
                }

                $tags = Tag::where('organization_id', $organizationId)
                    ->whereHas('categories', function ($query) use ($categoryIds) {
                        $query->whereIn('categories.id', $categoryIds);
                    })
                    ->with([
                        'users' => function ($query) {
                            $query->orderBy('id');
                        }
                    ])
                    ->get();

                foreach ($tags as $tag) {
                    if ($tag->users->isEmpty()) {
                        continue;
                    }

                    $activeUsers = $tag->users->filter(function ($user) {
                        $workSchedule = $user->work_schedule;
                        if (!$workSchedule || !isset($workSchedule['is_active'])) {
                            return true;
                        }
                        return $workSchedule['is_active'] === true;
                    });

                    if ($activeUsers->isEmpty()) {
                        continue;
                    }

                    $filteredUsers = $customerLanguage
                        ? $activeUsers->filter(function ($user) use ($customerLanguage) {
                            return !empty($user->languages) && in_array($customerLanguage, $user->languages);
                        })
                        : $activeUsers;

                    if ($filteredUsers->isEmpty()) {
                        $filteredUsers = $activeUsers;
                    }

                    $categoryIdsForCounting = [4];
                    $parentCategory = Category::find(4);
                    if ($parentCategory) {
                        $this->collectChildCategoryIds($parentCategory, $categoryIdsForCounting);
                    }

                    $lastAssignedCustomer = Customer::where('organization_id', $organizationId)
                        ->whereIn('user_id', $filteredUsers->pluck('id'))
                        ->whereIn('category_id', $categoryIdsForCounting)
                        ->orderBy('created_at', 'desc')
                        ->first();

                    if (!$lastAssignedCustomer) {
                        $data['user_id'] = $filteredUsers->first()->id;
                        break;
                    }

                    $lastUserIndex = $filteredUsers->values()->search(function ($user) use ($lastAssignedCustomer) {
                        return $user->id === $lastAssignedCustomer->user_id;
                    });

                    $nextUserIndex = ($lastUserIndex + 1) % $filteredUsers->count();
                    $data['user_id'] = $filteredUsers->values()->get($nextUserIndex)->id;
                    break;
                }
            }
        }

        if (empty($data['user_id'])) {
            $settings = Setting::where('organization_id', $organizationId)->first();

            if ($settings && !empty($settings->lead_assignment_settings)) {
                $assignmentSettings = $settings->lead_assignment_settings;

                if (isset($assignmentSettings['strategy']) && $assignmentSettings['strategy'] === 'sequential') {
                    $users = User::where('organization_id', $organizationId)
                        ->where('is_active', true)
                        ->whereHas('roles', function ($query) {
                            $query->where('roles.id', 3);
                        })
                        ->orderBy('id')
                        ->get();

                    $users = $users->filter(function ($user) {
                        $workSchedule = $user->work_schedule;
                        if (!$workSchedule || !isset($workSchedule['is_active'])) {
                            return true;
                        }
                        return $workSchedule['is_active'] === true;
                    });

                    if ($customerLanguage) {
                        $filteredUsers = $users->filter(function ($user) use ($customerLanguage) {
                            return !empty($user->languages) && in_array($customerLanguage, $user->languages);
                        });

                        if ($filteredUsers->isNotEmpty()) {
                            $users = $filteredUsers;
                        }
                    }

                    if ($users->isNotEmpty()) {
                        $categoryIdsForCounting = [4];
                        $parentCategory = Category::find(4);
                        if ($parentCategory) {
                            $this->collectChildCategoryIds($parentCategory, $categoryIdsForCounting);
                        }

                        $lastAssignedCustomer = Customer::whereIn('user_id', $users->pluck('id'))
                            ->where('organization_id', $organizationId)
                            ->whereIn('category_id', $categoryIdsForCounting)
                            ->orderBy('created_at', 'desc')
                            ->first();

                        if (!$lastAssignedCustomer) {
                            $data['user_id'] = $users->first()->id;
                        } else {
                            $lastUserIndex = $users->values()->search(function ($user) use ($lastAssignedCustomer) {
                                return $user->id === $lastAssignedCustomer->user_id;
                            });

                            $nextUserIndex = ($lastUserIndex + 1) % $users->count();
                            $data['user_id'] = $users->values()->get($nextUserIndex)->id;
                        }
                    }
                } elseif (isset($assignmentSettings['strategy']) && $assignmentSettings['strategy'] === 'working_hours') {
                    $now = Carbon::now();
                    $currentDay = $now->dayOfWeek;
                    $currentTime = $now->format('H:i');

                    $users = User::where('organization_id', $organizationId)
                        ->where('is_active', true)
                        ->whereHas('roles', function ($query) {
                            $query->where('roles.id', 3);
                        })
                        ->whereNotNull('work_schedule')
                        ->get();

                    if ($customerLanguage) {
                        $filteredUsers = $users->filter(function ($user) use ($customerLanguage) {
                            return !empty($user->languages) && in_array($customerLanguage, $user->languages);
                        });

                        if ($filteredUsers->isNotEmpty()) {
                            $users = $filteredUsers;
                        }
                    }

                    $availableUsers = $users->filter(function ($user) use ($currentDay, $currentTime) {
                        $workSchedule = $user->work_schedule;

                        if (!$workSchedule || !$workSchedule['is_active'])
                            return false;

                        $daySchedule = collect($workSchedule['days'])->firstWhere('day', $currentDay);
                        if (!$daySchedule || empty($daySchedule['times']))
                            return false;

                        foreach ($daySchedule['times'] as $timeSlot) {
                            if ($currentTime >= $timeSlot['start'] && $currentTime <= $timeSlot['end']) {
                                return true;
                            }
                        }
                        return false;
                    });

                    if ($availableUsers->isNotEmpty()) {
                        $categoryIdsForCounting = [4];
                        $parentCategory = Category::find(4);
                        if ($parentCategory) {
                            $this->collectChildCategoryIds($parentCategory, $categoryIdsForCounting);
                        }

                        $lastAssignedCustomer = Customer::whereIn('user_id', $availableUsers->pluck('id'))
                            ->where('organization_id', $organizationId)
                            ->whereIn('category_id', $categoryIdsForCounting)
                            ->orderBy('created_at', 'desc')
                            ->first();

                        if (!$lastAssignedCustomer) {
                            $data['user_id'] = $availableUsers->first()->id;
                        } else {
                            $lastUserIndex = $availableUsers->values()->search(function ($user) use ($lastAssignedCustomer) {
                                return $user->id === $lastAssignedCustomer->user_id;
                            });

                            $nextUserIndex = ($lastUserIndex + 1) % $availableUsers->count();
                            $data['user_id'] = $availableUsers->values()->get($nextUserIndex)->id;
                        }
                    }
                }
            }
        }

        $customer = Customer::create($data);
        $customer->load('organization', 'user', 'category', 'services', 'status');

        if ($customer->user_id) {
            $this->sendCustomerMessages($customer, $category, $tag);
            $this->sendUserNotification($customer);
        }

        return $customer;
    }

    private function sendCustomerMessages($customer, $category = null, $tag = null)
    {
        if ($tag) {
            $this->sendTagMessage($customer, $tag);
            return;
        }

        $settings = Setting::where('organization_id', $customer->organization_id)->first();
        if (!$settings || empty($settings->welcome_message_settings)) {
            return;
        }

        $messageSettings = $settings->welcome_message_settings;
        $channel = $category ? $category->channel : null;

        if ($channel) {
            switch ($channel) {
                case 'whatsapp':
                    if (!empty($messageSettings['whatsapp']) && $messageSettings['whatsapp']['status']) {
                        $this->sendWhatsappMessage($customer, $messageSettings['whatsapp']);
                    }
                    break;
                case 'sms':
                    if (!empty($messageSettings['sms']) && $messageSettings['sms']['status']) {
                        $this->sendSmsMessage($customer, $messageSettings['sms']);
                    }
                    break;
                case 'email':
                    if (!empty($messageSettings['email']) && $messageSettings['email']['status']) {
                        $this->sendEmailMessage($customer, $messageSettings['email']);
                    }
                    break;
                case 'phone':
                    if (!empty($messageSettings['phone']) && $messageSettings['phone']['status']) {
                        $this->sendPhoneCall($customer, $category);
                    }
                    break;
            }
        }
    }

    private function sendTagMessage($customer, $tag)
    {
        if (empty($tag->welcome_message)) {
            return;
        }

        $user = $customer->user;
        if (!$user) {
            return;
        }

        $customerName = explode(' ', trim($customer->name));
        $firstName = array_shift($customerName);

        $message = str_replace(
            ['{name}', '{user}'],
            [$firstName, $user->name],
            $tag->welcome_message
        );

        $settings = Setting::where('organization_id', $customer->organization_id)->first();
        if (!$settings || empty($settings->whatsapp_settings) || empty($settings->whatsapp_settings['api_url'])) {
            return;
        }

        $userSession = WhatsappSession::where('organization_id', $customer->organization_id)
            ->where('id', $user->whatsapp_session_id)
            ->first();

        if (!$userSession) {
            return;
        }

        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $phoneNumber = $phoneUtil->parse($customer->phone);
            $formattedPhone = $phoneUtil->format($phoneNumber, PhoneNumberFormat::E164);
            $formattedPhone = substr($formattedPhone, 1) . '@c.us';

            Http::withHeaders([
                'X-Api-Key' => $settings->whatsapp_settings['api_key']
            ])->post($settings->whatsapp_settings['api_url'] . '/sendText', [
                'chatId' => $formattedPhone,
                'text' => $message,
                'session' => $userSession->title
            ]);
        } catch (\Exception $e) {
        }
    }

    private function sendWhatsappMessage($customer, $whatsappSettings)
    {
        $currentTime = Carbon::now()->format('H:i:s');
        if ($currentTime < $whatsappSettings['start_time'] || $currentTime > $whatsappSettings['end_time']) {
            return;
        }

        $user = $customer->user;
        if (!$user) {
            return;
        }

        $settings = Setting::where('organization_id', $customer->organization_id)->first();
        if (!$settings || empty($settings->whatsapp_settings) || empty($settings->whatsapp_settings['api_url'])) {
            return;
        }

        $userSession = WhatsappSession::where('organization_id', $customer->organization_id)
            ->where('id', $user->whatsapp_session_id)
            ->first();

        if (!$userSession) {
            return;
        }

        $customerLanguage = $this->detectCustomerLanguage($customer);
        $message = $this->getMessageByLanguage($whatsappSettings['messages'], $customerLanguage);

        if (!$message) {
            return;
        }

        $customerName = explode(' ', trim($customer->name));
        $firstName = array_shift($customerName);

        $message = str_replace(
            ['{name}', '{user}'],
            [$firstName, $user->name],
            $message
        );

        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $phoneNumber = $phoneUtil->parse($customer->phone);
            $formattedPhone = $phoneUtil->format($phoneNumber, PhoneNumberFormat::E164);
            $formattedPhone = substr($formattedPhone, 1) . '@c.us';

            $apiUrl = $settings->whatsapp_settings['api_url'];

            if (!empty($whatsappSettings['file']) && !empty($whatsappSettings['file']['content'])) {
                $endpoint = $whatsappSettings['file']['type'] === 'image' ? '/sendImage' : '/sendFile';

                Http::withHeaders([
                    'X-Api-Key' => $settings->whatsapp_settings['api_key']
                ])->post($apiUrl . $endpoint, [
                    'chatId' => $formattedPhone,
                    'file' => [
                        'mimetype' => $whatsappSettings['file']['type'] === 'image' ? 'image/jpeg' : 'application/pdf',
                        'filename' => $whatsappSettings['file']['type'] === 'image' ? 'welcome.jpg' : 'welcome.pdf',
                        'data' => $whatsappSettings['file']['content']
                    ],
                    'caption' => $message,
                    'session' => $userSession->title
                ]);
            } else {
                Http::withHeaders([
                    'X-Api-Key' => $settings->whatsapp_settings['api_key']
                ])->post($apiUrl . '/sendText', [
                    'chatId' => $formattedPhone,
                    'text' => $message,
                    'session' => $userSession->title
                ]);
            }
        } catch (\Exception $e) {
        }
    }

    private function sendSmsMessage($customer, $smsSettings)
    {
        $currentTime = Carbon::now()->format('H:i:s');
        if ($currentTime < $smsSettings['start_time'] || $currentTime > $smsSettings['end_time']) {
            return;
        }

        $user = $customer->user;
        if (!$user) {
            return;
        }

        if (empty($customer->phone)) {
            return;
        }

        $settings = Setting::where('organization_id', $customer->organization_id)->first();
        if (!$settings || empty($settings->sms_settings)) {
            return;
        }

        $twilioSettings = $settings->sms_settings;
        if (empty($twilioSettings['account_sid']) || empty($twilioSettings['auth_token']) || empty($twilioSettings['phone_number'])) {
            return;
        }

        $customerLanguage = $this->detectCustomerLanguage($customer);
        $message = $this->getMessageByLanguage($smsSettings['messages'], $customerLanguage);

        if (!$message) {
            return;
        }

        $customerName = explode(' ', trim($customer->name));
        $firstName = array_shift($customerName);

        $message = str_replace(
            ['{name}', '{user}'],
            [$firstName, $user->name],
            $message
        );

        try {
            $client = new Client($twilioSettings['account_sid'], $twilioSettings['auth_token']);

            $client->messages->create(
                $customer->phone,
                [
                    'from' => $twilioSettings['phone_number'],
                    'body' => $message
                ]
            );
        } catch (\Exception $e) {
        }
    }

    private function sendEmailMessage($customer, $emailSettings)
    {
        $currentTime = Carbon::now()->format('H:i:s');
        if ($currentTime < $emailSettings['start_time'] || $currentTime > $emailSettings['end_time']) {
            return;
        }

        if (empty($customer->email)) {
            return;
        }

        $user = $customer->user;
        if (!$user) {
            return;
        }

        $settings = Setting::where('organization_id', $customer->organization_id)->first();
        if (!$settings || empty($settings->mail_settings)) {
            return;
        }

        $customerLanguage = $this->detectCustomerLanguage($customer);
        $messageTemplate = $this->getMessageByLanguage($emailSettings['messages'], $customerLanguage);

        if (!$messageTemplate) {
            return;
        }

        $customerName = explode(' ', trim($customer->name));
        $firstName = array_shift($customerName);

        $message = str_replace(
            ['{name}', '{user}'],
            [$firstName, $user->name],
            $messageTemplate
        );

        $smtpSettings = $settings->mail_settings;

        $transport = new EsmtpTransport($smtpSettings['smtp_host'], $smtpSettings['smtp_port']);
        $transport->setUsername($smtpSettings['smtp_username']);
        $transport->setPassword($smtpSettings['smtp_password']);

        try {
            $transport->start();
        } catch (\Exception $e) {
            return;
        }

        $mailer = new Mailer($transport);

        $organization = $customer->organization;

        $email = (new Email())
            ->from(new Address($smtpSettings['smtp_username'], $smtpSettings['smtp_from_name']))
            ->to(new Address($customer->email, $customer->name))
            ->subject('Welcome to ' . $organization->name)
            ->html(nl2br($message));

        try {
            $mailer->send($email);
        } catch (\Exception $e) {
        }
    }

    private function sendPhoneCall($customer, $category)
    {
        if (empty($customer->phone)) {
            return;
        }

        if (!$category || empty($category->vapi_assistant_id) || empty($category->vapi_phone_number_id)) {
            return;
        }

        $settings = Setting::where('organization_id', $customer->organization_id)->first();
        if (!$settings || empty($settings->vapi_settings) || empty($settings->vapi_settings['api_key'])) {
            return;
        }

        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $phoneNumber = $phoneUtil->parse($customer->phone);

            if (!$phoneUtil->isValidNumber($phoneNumber)) {
                return;
            }

            $formattedPhone = $phoneUtil->format($phoneNumber, PhoneNumberFormat::E164);

            $apiKey = $settings->vapi_settings['api_key'];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->post(env('VAPI_API_URL') . '/call', [
                'assistantId' => $category->vapi_assistant_id,
                'phoneNumberId' => $category->vapi_phone_number_id,
                'customer' => [
                    'number' => $formattedPhone
                ],
                'assistantOverrides' => [
                    'variableValues' => [
                        'id' => (string) $customer->id
                    ]
                ]
            ]);

        } catch (\Exception $e) {
        }
    }

    private function sendUserNotification($customer)
    {
        $user = $customer->user;
        if (!$user || !$user->whatsapp_session_id) {
            return;
        }

        $settings = Setting::where('organization_id', $customer->organization_id)->first();
        if (!$settings || empty($settings->whatsapp_settings) || empty($settings->whatsapp_settings['api_url'])) {
            return;
        }

        $adminSession = WhatsappSession::where('organization_id', $customer->organization_id)
            ->where('is_admin', true)
            ->first();

        if (!$adminSession || !$adminSession->phone) {
            return;
        }

        $userSession = WhatsappSession::where('id', $user->whatsapp_session_id)->first();
        if (!$userSession || !$userSession->phone) {
            return;
        }

        $notificationSettings = $settings->user_notification_settings ?? [];
        if (empty($notificationSettings['status']) || !$notificationSettings['status']) {
            return;
        }

        $messageTemplate = $notificationSettings['message_template'];
        
        $categoryName = $customer->category ? $customer->category->title : '-';
        $dateTime = Carbon::parse($customer->created_at)->format('d.m.Y H:i');
        $note = $customer->notes ?? '-';
        
        $message = str_replace(
            ['{customer_name}', '{customer_phone}', '{customer_id}', '{category_name}', '{date_time}', '{note}'],
            [$customer->name, $customer->phone, $customer->id, $categoryName, $dateTime, $note],
            $messageTemplate
        );

        try {
            Http::withHeaders([
                'X-Api-Key' => $settings->whatsapp_settings['api_key']
            ])->post($settings->whatsapp_settings['api_url'] . '/sendText', [
                'chatId' => $userSession->phone . '@c.us',
                'text' => $message,
                'session' => $adminSession->title
            ]);
        } catch (\Exception $e) {
        }
    }

    private function detectCustomerLanguage($customer)
    {
        if (empty($customer->phone)) {
            return null;
        }

        try {
            $phoneUtil = PhoneNumberUtil::getInstance();
            $phoneNumber = $phoneUtil->parse($customer->phone);
            return $phoneUtil->getRegionCodeForNumber($phoneNumber);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function getMessageByLanguage($messages, $language)
    {
        if (!$language) {
            return collect($messages)->firstWhere('is_default', true)['message'] ?? null;
        }

        $message = collect($messages)->firstWhere('language', strtolower($language));
        return $message ? $message['message'] : (collect($messages)->firstWhere('is_default', true)['message'] ?? null);
    }

    private function sendConfirmationEmail(Customer $customer)
    {
        if (empty($customer->email) || empty($customer->travel_info) || !is_array($customer->travel_info)) {
            return;
        }

        $settings = Setting::where('organization_id', $customer->organization_id)->first();
        if (!$settings || empty($settings->mail_settings) || empty($settings->sales_mail_settings)) {
            return;
        }

        $smtpSettings = $settings->mail_settings;
        $salesMailSettings = $settings->sales_mail_settings;

        if (empty($salesMailSettings['status']) || !$salesMailSettings['status'] || empty($salesMailSettings['message_template'])) {
            return;
        }

        $transport = new EsmtpTransport($smtpSettings['smtp_host'], $smtpSettings['smtp_port']);
        $transport->setUsername($smtpSettings['smtp_username']);
        $transport->setPassword($smtpSettings['smtp_password']);

        try {
            $transport->start();
        } catch (\Exception $e) {
            return;
        }

        $mailer = new Mailer($transport);

        $travelInfo = $customer->travel_info[count($customer->travel_info) - 1] ?? null;
        $organization = $customer->organization;

        $appointmentDate = $travelInfo && isset($travelInfo['appointment_date']) ? Carbon::parse($travelInfo['appointment_date'])->format('d.m.Y') : '';
        $serviceNames = $customer->services->pluck('title')->implode(', ');
        $serviceName = $serviceNames ?: '';

        $partnerHotel = null;
        $hotelName = '';
        if ($travelInfo) {
            if ($travelInfo['is_custom_hotel'] && !empty($travelInfo['hotel_name'])) {
                $hotelName = $travelInfo['hotel_name'];
            } else if (isset($travelInfo['partner_hotel_id'])) {
                $partnerHotel = PartnerHotel::find($travelInfo['partner_hotel_id']);
                $hotelName = $partnerHotel ? $partnerHotel->name : '';
            }
        }

        $checkIn = $travelInfo && isset($travelInfo['arrival_date']) ? Carbon::parse($travelInfo['arrival_date'])->format('d.m.Y') : '';
        $checkOut = $travelInfo && isset($travelInfo['departure_date']) ? Carbon::parse($travelInfo['departure_date'])->format('d.m.Y') : '';

        $replacements = [
            '{name}' => $customer->name,
            '{organization_name}' => $organization->name,
            '{appointment_date}' => $appointmentDate,
            '{service_name}' => $serviceName,
            '{hotel_name}' => $hotelName,
            '{check_in}' => $checkIn,
            '{check_out}' => $checkOut,
        ];

        $emailContent = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $salesMailSettings['message_template']
        );

        $email = (new Email())
            ->from(new Address($smtpSettings['smtp_username'], $smtpSettings['smtp_from_name']))
            ->to(new Address($customer->email, $customer->name))
            ->subject($salesMailSettings['subject'] ?? 'Appointment and Accommodation Confirmation')
            ->html(nl2br($emailContent));

        try {
            $mailer->send($email);
        } catch (\Exception $e) {
        }
    }

    private function sendHotelMessage(Customer $customer, $statusId)
    {
        $lastTravelInfo = $customer->travel_info && is_array($customer->travel_info) && count($customer->travel_info) > 0 ?
            $customer->travel_info[count($customer->travel_info) - 1] : null;

        if (!$lastTravelInfo || $lastTravelInfo['is_custom_hotel'] || !isset($lastTravelInfo['partner_hotel_id']) || empty($lastTravelInfo['partner_hotel_id'])) {
            return;
        }

        $partnerHotel = PartnerHotel::find($lastTravelInfo['partner_hotel_id']);
        if (!$partnerHotel || empty($partnerHotel->chat_id) || empty($partnerHotel->message_templates)) {
            return;
        }

        $settings = Setting::where('organization_id', $customer->organization_id)->first();
        if (!$settings || empty($settings->whatsapp_settings) || empty($settings->whatsapp_settings['api_url'])) {
            return;
        }

        $adminSession = WhatsappSession::where('organization_id', $customer->organization_id)
            ->where('is_admin', true)
            ->first();

        if (!$adminSession) {
            return;
        }

        $messageTemplates = $partnerHotel->message_templates;
        $templateKey = $statusId == 8 ? 'sale' : 'cancel';

        if (empty($messageTemplates[$templateKey])) {
            return;
        }

        $messageTemplate = $messageTemplates[$templateKey];

        $travelInfoLast = $customer->travel_info && is_array($customer->travel_info) && count($customer->travel_info) > 0 ?
            $customer->travel_info[count($customer->travel_info) - 1] : null;

        $checkIn = $travelInfoLast ? $travelInfoLast['arrival_date'] ?? '' : '';
        $checkOut = $travelInfoLast ? $travelInfoLast['departure_date'] ?? '' : '';
        $roomType = $travelInfoLast ? $travelInfoLast['room_type'] ?? '' : '';

        if ($checkIn) {
            $checkIn = Carbon::parse($checkIn)->format('d.m.Y');
        }
        if ($checkOut) {
            $checkOut = Carbon::parse($checkOut)->format('d.m.Y');
        }

        $replacements = [
            '{name}' => $customer->name,
            '{check_in}' => $checkIn,
            '{check_out}' => $checkOut,
            '{room_type}' => $roomType,
        ];

        $message = str_replace(array_keys($replacements), array_values($replacements), $messageTemplate);

        try {
            Http::withHeaders([
                'X-Api-Key' => $settings->whatsapp_settings['api_key']
            ])->post($settings->whatsapp_settings['api_url'] . '/sendText', [
                'chatId' => $partnerHotel->chat_id,
                'text' => $message,
                'session' => $adminSession->title
            ]);
        } catch (\Exception $e) {
        }
    }

    private function sendHotelEmail(Customer $customer, $statusId)
    {
        $lastTravelInfo = $customer->travel_info && is_array($customer->travel_info) && count($customer->travel_info) > 0 ?
            $customer->travel_info[count($customer->travel_info) - 1] : null;

        if (!$lastTravelInfo || $lastTravelInfo['is_custom_hotel'] || !isset($lastTravelInfo['partner_hotel_id']) || empty($lastTravelInfo['partner_hotel_id'])) {
            return;
        }

        $partnerHotel = PartnerHotel::find($lastTravelInfo['partner_hotel_id']);
        if (!$partnerHotel || empty($partnerHotel->email) || empty($partnerHotel->message_templates)) {
            return;
        }

        $settings = Setting::where('organization_id', $customer->organization_id)->first();
        if (!$settings || empty($settings->mail_settings)) {
            return;
        }

        $messageTemplates = $partnerHotel->message_templates;
        $templateKey = $statusId == 8 ? 'sale' : 'cancel';

        if (empty($messageTemplates[$templateKey])) {
            return;
        }

        $messageTemplate = $messageTemplates[$templateKey];

        $smtpSettings = $settings->mail_settings;

        $transport = new EsmtpTransport($smtpSettings['smtp_host'], $smtpSettings['smtp_port']);
        $transport->setUsername($smtpSettings['smtp_username']);
        $transport->setPassword($smtpSettings['smtp_password']);

        try {
            $transport->start();
        } catch (\Exception $e) {
            return;
        }

        $mailer = new Mailer($transport);

        $travelInfoLast = $customer->travel_info && is_array($customer->travel_info) && count($customer->travel_info) > 0 ?
            $customer->travel_info[count($customer->travel_info) - 1] : null;

        $checkIn = $travelInfoLast ? $travelInfoLast['arrival_date'] ?? '' : '';
        $checkOut = $travelInfoLast ? $travelInfoLast['departure_date'] ?? '' : '';
        $roomType = $travelInfoLast ? $travelInfoLast['room_type'] ?? '' : '';

        if ($checkIn) {
            $checkIn = Carbon::parse($checkIn)->format('d.m.Y');
        }
        if ($checkOut) {
            $checkOut = Carbon::parse($checkOut)->format('d.m.Y');
        }

        $replacements = [
            '{name}' => $customer->name,
            '{check_in}' => $checkIn,
            '{check_out}' => $checkOut,
            '{room_type}' => $roomType,
        ];

        $emailContent = str_replace(array_keys($replacements), array_values($replacements), $messageTemplate);

        $subject = $statusId == 8 ? 'Yeni Rezervasyon Bildirimi' : 'Rezervasyon İptal Bildirimi';

        $email = (new Email())
            ->from(new Address($smtpSettings['smtp_username'], $smtpSettings['smtp_from_name']))
            ->to(new Address($partnerHotel->email, $partnerHotel->name))
            ->subject($subject)
            ->html(nl2br($emailContent));

        try {
            $mailer->send($email);
        } catch (\Exception $e) {
        }
    }

    private function sendSalesNotification(Customer $customer)
    {
        $settings = Setting::where('organization_id', $customer->organization_id)->first();
        if (!$settings || empty($settings->sales_notification_settings) || !$settings->sales_notification_settings['status']) {
            return;
        }

        $notificationSettings = $settings->sales_notification_settings;
        if (empty($notificationSettings['chat_id'])) {
            return;
        }

        if (empty($settings->whatsapp_settings) || empty($settings->whatsapp_settings['api_url'])) {
            return;
        }

        $adminSession = WhatsappSession::where('organization_id', $customer->organization_id)
            ->where('is_admin', true)
            ->first();

        if (!$adminSession) {
            return;
        }

        $travelInfo = $customer->travel_info && is_array($customer->travel_info) && count($customer->travel_info) > 0 ?
            $customer->travel_info[count($customer->travel_info) - 1] : null;

        $appointmentDate = $travelInfo && isset($travelInfo['appointment_date']) ? Carbon::parse($travelInfo['appointment_date'])->format('d.m.Y') : '';

        $replacements = [
            '{name}' => $customer->name,
            '{date}' => Carbon::parse($customer->created_at)->format('d.m.Y'),
            '{category}' => $customer->category ? $customer->category->title : '',
            '{service}' => $customer->services->pluck('title')->implode(', '),
            '{appointment_date}' => $appointmentDate,
            '{user}' => $customer->user ? $customer->user->name : ''
        ];

        $message = str_replace(
            array_keys($replacements),
            array_values($replacements),
            $notificationSettings['message_template']
        );

        try {
            Http::withHeaders([
                'X-Api-Key' => $settings->whatsapp_settings['api_key']
            ])->post($settings->whatsapp_settings['api_url'] . '/sendText', [
                'chatId' => $notificationSettings['chat_id'],
                'text' => $message,
                'session' => $adminSession->title
            ]);
        } catch (\Exception $e) {
        }
    }

    private function sendTransferMessage(Customer $customer, $statusId)
    {
        $lastTravelInfo = $customer->travel_info && is_array($customer->travel_info) && count($customer->travel_info) > 0 ?
            $customer->travel_info[count($customer->travel_info) - 1] : null;

        if (!$lastTravelInfo || !isset($lastTravelInfo['partner_transfer_id']) || empty($lastTravelInfo['partner_transfer_id'])) {
            return;
        }

        $partnerTransfer = PartnerTransfer::find($lastTravelInfo['partner_transfer_id']);
        if (!$partnerTransfer || empty($partnerTransfer->chat_id) || empty($partnerTransfer->message_templates)) {
            return;
        }

        $settings = Setting::where('organization_id', $customer->organization_id)->first();
        if (!$settings || empty($settings->whatsapp_settings) || empty($settings->whatsapp_settings['api_url'])) {
            return;
        }

        $adminSession = WhatsappSession::where('organization_id', $customer->organization_id)
            ->where('is_admin', true)
            ->first();

        if (!$adminSession) {
            return;
        }

        $messageTemplates = $partnerTransfer->message_templates;
        $templateKey = $statusId == 8 ? 'reservation' : 'cancel';

        if (empty($messageTemplates[$templateKey])) {
            return;
        }

        $messageTemplate = $messageTemplates[$templateKey];

        $travelInfoLast = $customer->travel_info && is_array($customer->travel_info) && count($customer->travel_info) > 0 ?
            $customer->travel_info[count($customer->travel_info) - 1] : null;

        $arrivalDate = $travelInfoLast ? $travelInfoLast['arrival_date'] ?? '' : '';
        $arrivalTime = $travelInfoLast ? $travelInfoLast['arrival_time'] ?? '' : '';
        $arrivalFlightCode = $travelInfoLast ? $travelInfoLast['arrival_flight_code'] ?? '' : '';
        $departureDate = $travelInfoLast ? $travelInfoLast['departure_date'] ?? '' : '';
        $departureTime = $travelInfoLast ? $travelInfoLast['departure_time'] ?? '' : '';
        $departureFlightCode = $travelInfoLast ? $travelInfoLast['departure_flight_code'] ?? '' : '';
        $personCount = $travelInfoLast ? $travelInfoLast['person_count'] ?? '' : '';

        $hotelName = '';
        if ($travelInfoLast) {
            if ($travelInfoLast['is_custom_hotel'] && !empty($travelInfoLast['hotel_name'])) {
                $hotelName = $travelInfoLast['hotel_name'];
            } else if (isset($travelInfoLast['partner_hotel_id'])) {
                $partnerHotel = PartnerHotel::find($travelInfoLast['partner_hotel_id']);
                $hotelName = $partnerHotel ? $partnerHotel->name : '';
            }
        }

        if ($arrivalDate) {
            $arrivalDate = Carbon::parse($arrivalDate)->format('d.m.Y');
        }
        if ($departureDate) {
            $departureDate = Carbon::parse($departureDate)->format('d.m.Y');
        }

        if ($departureTime) {
            try {
                $departureTimeCarbon = Carbon::createFromFormat('H:i', $departureTime);
                $departureTime = $departureTimeCarbon->subHours(3)->format('H:i');
            } catch (\Exception $e) {
            }
        }

        $replacements = [
            '{name}' => $customer->name,
            '{person_count}' => $personCount,
            '{hotel_name}' => $hotelName,
            '{user}' => $customer->user ? $customer->user->name : '',
            '{arrival_date}' => $arrivalDate,
            '{arrival_time}' => $arrivalTime,
            '{arrival_flight_code}' => $arrivalFlightCode,
            '{departure_date}' => $departureDate,
            '{departure_time}' => $departureTime,
            '{departure_flight_code}' => $departureFlightCode,
        ];

        $message = str_replace(array_keys($replacements), array_values($replacements), $messageTemplate);

        try {
            Http::withHeaders([
                'X-Api-Key' => $settings->whatsapp_settings['api_key']
            ])->post($settings->whatsapp_settings['api_url'] . '/sendText', [
                'chatId' => $partnerTransfer->chat_id,
                'text' => $message,
                'session' => $adminSession->title
            ]);
        } catch (\Exception $e) {
        }
    }

    private function collectChildCategoryIds($category, &$ids)
    {
        foreach ($category->children as $child) {
            $ids[] = $child->id;
            $this->collectChildCategoryIds($child, $ids);
        }
    }
}
