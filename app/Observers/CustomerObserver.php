<?php

namespace App\Observers;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLog;
use App\Models\Organization;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class CustomerObserver
{
    public function creating(Customer $customer): void
    {
        if (!$customer->status_id) {
            $customer->status_id = 1;
        }
    }

    public function created(Customer $customer): void
    {
        $logData = [
            'customer_id' => $customer->id,
            'user_id' => app()->runningInConsole() ? null : Auth::id(),
            'field_name' => 'customer',
            'action_type' => 'create',
            'old_value' => null,
            'new_value' => json_encode($customer)
        ];

        CustomerLog::create($logData);
    }

    public function updated(Customer $customer): void
    {
        $changes = $customer->getDirty();

        unset($changes['duplicate_count']);
        unset($changes['duplicate_checked']);
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        foreach ($changes as $field => $newValue) {
            $oldValue = $customer->getOriginal($field);

            if ($oldValue === $newValue) {
                continue;
            }

            $logData = [
                'customer_id' => $customer->id,
                'user_id' => Auth::id() ?? null,
                'field_name' => $field,
                'action_type' => 'update'
            ];

            switch ($field) {
                case 'organization_id':
                    $oldOrg = Organization::find($oldValue);
                    $newOrg = Organization::find($newValue);
                    $logData['old_value'] = $oldOrg ? json_encode($oldOrg) : null;
                    $logData['new_value'] = $newOrg ? json_encode($newOrg) : null;
                    break;
                case 'user_id':
                    $oldUser = User::find($oldValue);
                    $newUser = User::find($newValue);
                    $logData['old_value'] = $oldUser ? json_encode($oldUser) : null;
                    $logData['new_value'] = $newUser ? json_encode($newUser) : null;
                    break;
                case 'category_id':
                    $oldCategory = Category::find($oldValue);
                    $newCategory = Category::find($newValue);
                    $logData['old_value'] = $oldCategory ? json_encode($oldCategory) : null;
                    $logData['new_value'] = $newCategory ? json_encode($newCategory) : null;
                    break;
                case 'status_id':
                    $oldStatus = Status::find($oldValue);
                    $newStatus = Status::find($newValue);
                    $logData['old_value'] = $oldStatus ? json_encode($oldStatus) : null;
                    $logData['new_value'] = $newStatus ? json_encode($newStatus) : null;
                    break;
                case 'phone_calls':
                case 'reminder':
                case 'sales_info':
                case 'travel_info':
                    $logData['old_value'] = is_string($oldValue) ? $oldValue : json_encode($oldValue);
                    $logData['new_value'] = is_string($newValue) ? $newValue : json_encode($newValue);
                    break;
                default:
                    $logData['old_value'] = $oldValue;
                    $logData['new_value'] = $newValue;
            }

            CustomerLog::create($logData);
        }
    }
}
