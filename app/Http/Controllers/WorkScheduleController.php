<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkScheduleController extends Controller
{
    public function index(Request $request)
    {
        if (Gate::none(['work_schedule_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $settings = Setting::where('organization_id', $organizationId)->first();
        $strategy = 'sequential';

        if ($settings && !empty($settings->lead_assignment_settings)) {
            $strategy = $settings->lead_assignment_settings['strategy'] ?? 'sequential';
        }

        $users = User::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereHas('roles', function ($query) {
                $query->where('roles.id', 3);
            })
            ->orderBy('id')
            ->get();

        $nextUserId = null;

        if ($strategy === 'sequential') {
            $activeUsers = $users->filter(function ($user) {
                $workSchedule = $user->work_schedule;
                return $workSchedule && isset($workSchedule['is_active']) && $workSchedule['is_active'] === true;
            });

            if ($activeUsers->isNotEmpty()) {
                $categoryIdsForCounting = [4];
                $parentCategory = Category::find(4);
                if ($parentCategory) {
                    $this->collectChildCategoryIds($parentCategory, $categoryIdsForCounting);
                }

                $lastAssignedCustomer = Customer::whereIn('user_id', $activeUsers->pluck('id'))
                    ->where('organization_id', $organizationId)
                    ->whereIn('category_id', $categoryIdsForCounting)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if (!$lastAssignedCustomer) {
                    $nextUserId = $activeUsers->first()->id ?? null;
                } else {
                    $lastUserIndex = $activeUsers->values()->search(function ($user) use ($lastAssignedCustomer) {
                        return $user->id === $lastAssignedCustomer->user_id;
                    });

                    $nextUserIndex = ($lastUserIndex + 1) % $activeUsers->count();
                    $nextUserId = $activeUsers->values()->get($nextUserIndex)->id ?? null;
                }
            }
        } elseif ($strategy === 'working_hours') {
            $now = Carbon::now();
            $currentDay = $now->dayOfWeek;
            $currentTime = $now->format('H:i');

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
                    $nextUserId = $availableUsers->first()->id ?? null;
                } else {
                    $lastUserIndex = $availableUsers->values()->search(function ($user) use ($lastAssignedCustomer) {
                        return $user->id === $lastAssignedCustomer->user_id;
                    });

                    $nextUserIndex = ($lastUserIndex + 1) % $availableUsers->count();
                    $nextUserId = $availableUsers->values()->get($nextUserIndex)->id ?? null;
                }
            }
        }

        return response()->json([
            'users' => $users,
            'strategy' => $strategy,
            'nextUserId' => $nextUserId,
            'currentTime' => Carbon::now()->format('H:i'),
            'currentDay' => Carbon::now()->dayOfWeek
        ]);
    }

    public function getWorkingStatus(Request $request)
    {
        if (Gate::none(['work_schedule_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');

        $settings = Setting::where('organization_id', $organizationId)->first();
        $strategy = 'sequential';

        if ($settings && !empty($settings->lead_assignment_settings)) {
            $strategy = $settings->lead_assignment_settings['strategy'] ?? 'sequential';
        }

        $now = Carbon::now();
        $currentDay = $now->dayOfWeek;
        $currentTime = $now->format('H:i');

        $users = User::where('organization_id', $organizationId)
            ->where('is_active', true)
            ->whereHas('roles', function ($query) {
                $query->where('roles.id', 3);
            })
            ->orderBy('id')
            ->get();

        $workingStatuses = [];

        foreach ($users as $user) {
            $isWorking = false;
            $nextWorkTime = null;
            $workSchedule = $user->work_schedule;

            if ($strategy === 'sequential') {
                $isWorking = $workSchedule && isset($workSchedule['is_active']) && $workSchedule['is_active'] === true;
            } else {
                if ($workSchedule && $workSchedule['is_active']) {
                    $daySchedule = collect($workSchedule['days'])->firstWhere('day', $currentDay);

                    if ($daySchedule && !empty($daySchedule['times'])) {
                        foreach ($daySchedule['times'] as $timeSlot) {
                            if ($currentTime >= $timeSlot['start'] && $currentTime <= $timeSlot['end']) {
                                $isWorking = true;
                                break;
                            }

                            if ($currentTime < $timeSlot['start'] && !$nextWorkTime) {
                                $nextWorkTime = $timeSlot['start'];
                            }
                        }
                    }
                }
            }

            $workingStatuses[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'is_working' => $isWorking,
                'next_work_time' => $nextWorkTime,
                'work_schedule' => $workSchedule
            ];
        }

        return response()->json([
            'working_statuses' => $workingStatuses,
            'current_time' => $currentTime,
            'current_day' => $currentDay,
            'strategy' => $strategy
        ]);
    }

    private function collectChildCategoryIds($category, &$ids)
    {
        foreach ($category->children as $child) {
            $ids[] = $child->id;
            $this->collectChildCategoryIds($child, $ids);
        }
    }
}
