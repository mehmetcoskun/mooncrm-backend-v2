<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $organizationId = $user->organization_id ?? request()->header('X-Organization-Id');
        $userRoleIds = $user->roles->pluck('id')->toArray();

        $customersQuery = Customer::where('customers.organization_id', $organizationId);

        if (in_array(3, $userRoleIds) || in_array(7, $userRoleIds) && !in_array(1, $userRoleIds) && !in_array(2, $userRoleIds)) {
            $customersQuery->where('customers.user_id', $user->id);
        }

        $thirtyDaysAgo = Carbon::now()->subDays(30);

        $totalCustomers = $customersQuery->count();
        $totalCustomersLastMonth = (clone $customersQuery)
            ->where('customers.created_at', '<', $thirtyDaysAgo)
            ->count();
        $totalCustomersGrowth = $totalCustomersLastMonth > 0
            ? (($totalCustomers - $totalCustomersLastMonth) / $totalCustomersLastMonth) * 100
            : 100;

        $totalSales = (clone $customersQuery)
            ->where('customers.status_id', 8)
            ->count();
        $totalSalesLastMonth = (clone $customersQuery)
            ->where('customers.status_id', 8)
            ->where('customers.created_at', '<', $thirtyDaysAgo)
            ->count();
        $totalSalesGrowth = $totalSalesLastMonth > 0
            ? (($totalSales - $totalSalesLastMonth) / $totalSalesLastMonth) * 100
            : 100;

        $totalProposals = (clone $customersQuery)
            ->whereHas('services')
            ->count();
        $totalProposalsLastMonth = (clone $customersQuery)
            ->whereHas('services')
            ->where('customers.created_at', '<', $thirtyDaysAgo)
            ->count();
        $totalProposalsGrowth = $totalProposalsLastMonth > 0
            ? (($totalProposals - $totalProposalsLastMonth) / $totalProposalsLastMonth) * 100
            : 100;

        $conversionRate = $totalProposals > 0 ? ($totalSales / $totalProposals) * 100 : 0;
        $conversionRateLastMonth = $totalProposalsLastMonth > 0
            ? (($totalSalesLastMonth / $totalProposalsLastMonth) * 100)
            : 0;
        $conversionRateGrowth = $conversionRateLastMonth > 0
            ? (($conversionRate - $conversionRateLastMonth) / $conversionRateLastMonth) * 100
            : 100;

        $monthlyCustomers = [];
        $monthlyProposals = [];
        $monthlySales = [];

        for ($i = 11; $i >= 0; $i--) {
            $startDate = Carbon::now()->subMonths($i)->startOfMonth();
            $endDate = Carbon::now()->subMonths($i)->endOfMonth();

            $customerCount = (clone $customersQuery)
                ->whereBetween('customers.created_at', [$startDate, $endDate])
                ->count();
            $monthlyCustomers[] = $customerCount;

            $proposalCount = (clone $customersQuery)
                ->whereHas('services')
                ->where('customers.status_id', '!=', 8)
                ->whereBetween('customers.created_at', [$startDate, $endDate])
                ->count();
            $monthlyProposals[] = $proposalCount;

            $saleCount = (clone $customersQuery)
                ->where('customers.status_id', 8)
                ->whereBetween('customers.created_at', [$startDate, $endDate])
                ->count();
            $monthlySales[] = $saleCount;
        }

        $categoryQuery = Customer::selectRaw('categories.title, COUNT(*) as count')
            ->join('categories', 'customers.category_id', '=', 'categories.id')
            ->where('customers.organization_id', $organizationId);

        if (in_array(3, $userRoleIds) || in_array(7, $userRoleIds) && !in_array(1, $userRoleIds) && !in_array(2, $userRoleIds)) {
            $categoryQuery->where('customers.user_id', $user->id);
        }

        $categoryDistribution = $categoryQuery->groupBy('categories.id', 'categories.title')
            ->orderByDesc('count')
            ->limit(5)
            ->get()
            ->map(function ($item) use ($totalCustomers) {
                return [
                    'label' => $item->title,
                    'value' => ($item->count / $totalCustomers) * 100
                ];
            });

        $statusQuery = Customer::selectRaw('statuses.title, COUNT(*) as count')
            ->join('statuses', 'customers.status_id', '=', 'statuses.id')
            ->where('customers.organization_id', $organizationId);

        if (in_array(3, $userRoleIds) || in_array(7, $userRoleIds) && !in_array(1, $userRoleIds) && !in_array(2, $userRoleIds)) {
            $statusQuery->where('customers.user_id', $user->id);
        }

        $statusDistribution = $statusQuery->groupBy('statuses.id', 'statuses.title')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) use ($totalCustomers) {
                return [
                    'label' => $item->title,
                    'value' => ($item->count / $totalCustomers) * 100
                ];
            });

        $remindersQuery = Customer::where('customers.organization_id', $organizationId)
            ->whereRaw("JSON_EXTRACT(reminder, '$.status') = true")
            ->whereRaw("JSON_EXTRACT(reminder, '$.date') >= ?", [Carbon::now()]);

        if (in_array(3, $userRoleIds) || in_array(7, $userRoleIds) && !in_array(1, $userRoleIds) && !in_array(2, $userRoleIds)) {
            $remindersQuery->where('customers.user_id', $user->id);
        }

        $upcomingReminders = $remindersQuery
            ->orderByRaw("JSON_EXTRACT(reminder, '$.date')")
            ->limit(4)
            ->get()
            ->map(function ($customer) {
                return [
                    'id' => $customer->id,
                    'title' => $customer->reminder['notes'] ?? '',
                    'date' => $customer->reminder['date'] ?? '',
                    'user' => $customer->user->name,
                    'customer' => $customer->name
                ];
            });

        $countryQuery = Customer::selectRaw('country, COUNT(*) as count')
            ->where('organization_id', $organizationId)
            ->whereNotNull('country')
            ->where('country', '!=', '');

        if (in_array(3, $userRoleIds) || in_array(7, $userRoleIds) && !in_array(1, $userRoleIds) && !in_array(2, $userRoleIds)) {
            $countryQuery->where('user_id', $user->id);
        }

        $countryDistribution = $countryQuery->groupBy('country')
            ->orderByDesc('count')
            ->get()
            ->map(function ($item) {
                return [
                    'country' => $item->country,
                    'value' => (int) $item->count
                ];
            });

        return response()->json([
            'stats' => [
                'totalCustomers' => $totalCustomers,
                'totalCustomersGrowth' => round($totalCustomersGrowth, 1),
                'totalSales' => $totalSales,
                'totalSalesGrowth' => round($totalSalesGrowth, 1),
                'totalProposals' => $totalProposals,
                'totalProposalsGrowth' => round($totalProposalsGrowth, 1),
                'conversionRate' => round($conversionRate, 1),
                'conversionRateGrowth' => round($conversionRateGrowth, 1)
            ],
            'monthlyCustomers' => $monthlyCustomers,
            'monthlyProposals' => $monthlyProposals,
            'monthlySales' => $monthlySales,
            'categoryDistribution' => $categoryDistribution,
            'statusDistribution' => $statusDistribution,
            'upcomingReminders' => $upcomingReminders,
            'countryDistribution' => $countryDistribution
        ]);
    }
}