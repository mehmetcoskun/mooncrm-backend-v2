<?php

namespace App\Http\Controllers;

use App\Traits\FilterableTrait;
use App\Models\Category;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    use FilterableTrait;

    public function getReports(Request $request)
    {
        $organizationId = auth()->user()->organization_id ?? request()->header('X-Organization-Id');

        $usersQuery = User::where('organization_id', $organizationId)
            ->whereHas('roles', function ($query) {
                $query->where('roles.id', 3);
            });

        if ($request->has('users') && !empty($request->users)) {
            if (isset($request->users) && is_array($request->users)) {
                $operator = $request->input('users_operator', 'in');
                if ($operator === 'in') {
                    $usersQuery->whereIn('id', $request->users);
                } elseif ($operator === 'nin') {
                    $usersQuery->whereNotIn('id', $request->users);
                }
            }
        }

        $users = $usersQuery->get();

        $reports = [];

        foreach ($users as $user) {
            $customersQuery = Customer::where('organization_id', $organizationId)
                ->where('user_id', $user->id);


            $this->applyAdvancedFilters($customersQuery, $request);

            $contacts = $customersQuery->count();

            $offers = (clone $customersQuery)
                ->whereHas('services')
                ->count();

            $sales = (clone $customersQuery)
                ->where('status_id', 8)
                ->count();

            $canceled = (clone $customersQuery)
                ->where('status_id', 9)
                ->count();

            $offerPercentage = $contacts > 0 ? round(($offers / $contacts) * 100, 2) : 0;
            $salesPercentage = $offers > 0 ? round(($sales / $offers) * 100, 2) : 0;
            $canceledPercentage = ($sales + $canceled) > 0 ? round(($canceled / ($sales + $canceled)) * 100, 2) : 0;

            $reports[] = [
                'name' => $user->name,
                'contacts' => [
                    'total' => $contacts,
                    'percentage' => 100
                ],
                'offers' => [
                    'total' => $offers,
                    'percentage' => $offerPercentage
                ],
                'sales' => [
                    'total' => $sales,
                    'percentage' => $salesPercentage
                ],
                'canceled' => [
                    'total' => $canceled,
                    'percentage' => $canceledPercentage
                ]
            ];
        }

        return response()->json($reports);
    }

}
