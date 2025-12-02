<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Doctor;
use App\Models\PartnerHotel;
use App\Models\PartnerTransfer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Carbon\Carbon;

class AppointmentController extends Controller
{
    public function getAppointments(Request $request)
    {
        if (Gate::none(['appointment_Access', 'calendar_Access']))
            return response()->json(['message' => 'Unauthorized'], 403);

        $organizationId = auth()->user()->organization_id ?? $request->header('X-Organization-Id');
        $month = $request->query('month', Carbon::now()->month);
        $year = $request->query('year', Carbon::now()->year);

        $user = auth()->user();
        $userRoles = $user->roles;
        $userRoleIds = $userRoles->pluck('id')->toArray();

        $customers = Customer::with(['user', 'services'])
            ->select('id', 'user_id', 'name', 'phone', 'email', 'reminder', 'sales_info', 'travel_info')
            ->when($organizationId, function ($query) use ($organizationId) {
                return $query->where('organization_id', $organizationId);
            })
            ->where('organization_id', $organizationId)
            ->where('status_id', 8)
            ->where(function ($query) {
                $query->whereNotNull('reminder')
                    ->orWhereNotNull('sales_info')
                    ->orWhereNotNull('travel_info');
            })
            ->where(function ($query) use ($month, $year) {
                $query->whereRaw("JSON_SEARCH(
                    JSON_EXTRACT(travel_info, '$[*].appointment_date'),
                    'one',
                    CONCAT(?, '-%'),
                    NULL,
                    '$[*]'
                ) IS NOT NULL", [sprintf('%04d-%02d', $year, $month)]);
            });

        if (in_array(7, $userRoleIds)) {
            $customers->where('user_id', $user->id);
        }

        $results = $customers->get();

        $doctorIds = [];
        $hotelIds = [];
        $transferIds = [];

        foreach ($results as $customer) {
            if (!empty($customer->travel_info) && is_array($customer->travel_info)) {
                foreach ($customer->travel_info as $travel) {
                    if (isset($travel['doctor_id'])) {
                        $doctorIds[] = $travel['doctor_id'];
                    }
                    if (isset($travel['partner_hotel_id'])) {
                        $hotelIds[] = $travel['partner_hotel_id'];
                    }
                    if (isset($travel['partner_transfer_id'])) {
                        $transferIds[] = $travel['partner_transfer_id'];
                    }
                }
            }
        }

        $doctors = [];
        $hotels = [];
        $transfers = [];

        if (!empty($doctorIds)) {
            $doctors = Doctor::whereIn('id', array_unique($doctorIds))->get()->keyBy('id');
        }

        if (!empty($hotelIds)) {
            $hotels = PartnerHotel::whereIn('id', array_unique($hotelIds))->get()->keyBy('id');
        }

        if (!empty($transferIds)) {
            $transfers = PartnerTransfer::whereIn('id', array_unique($transferIds))->get()->keyBy('id');
        }

        $finalResults = [];

        foreach ($results as $customer) {
            if (!empty($customer->travel_info) && is_array($customer->travel_info)) {
                $travelInfo = $customer->travel_info;
                $filteredTravels = [];
                $travelNumbers = [];
                
                foreach ($travelInfo as $travelIndex => $travel) {
                    if (isset($travel['appointment_date']) && !empty($travel['appointment_date'])) {
                        $appointmentDate = Carbon::parse($travel['appointment_date']);
                        if ($appointmentDate->month == $month && $appointmentDate->year == $year) {
                            $filteredTravels[] = $travel;
                            $travelNumbers[] = $travelIndex + 1;
                        }
                    }
                }
                
                foreach ($filteredTravels as $index => &$travel) {
                    if (isset($travel['doctor_id']) && isset($doctors[$travel['doctor_id']])) {
                        $travel['doctor'] = $doctors[$travel['doctor_id']]->toArray();
                    }

                    if (isset($travel['partner_hotel_id']) && isset($hotels[$travel['partner_hotel_id']])) {
                        $travel['partner_hotel'] = $hotels[$travel['partner_hotel_id']]->toArray();
                    }

                    if (isset($travel['partner_transfer_id']) && isset($transfers[$travel['partner_transfer_id']])) {
                        $travel['partner_transfer'] = $transfers[$travel['partner_transfer_id']]->toArray();
                    }

                    $customerCopy = clone $customer;
                    $customerCopy->travel_info = [$travel];
                    $customerCopy->travel_count = $travelNumbers[$index];
                    $customerCopy->total_travels = count($travelInfo);

                    $finalResults[] = $customerCopy;
                }
            } else {
                $finalResults[] = $customer;
            }
        }

        usort($finalResults, function ($a, $b) {
            $aTravel = $a->travel_info[0] ?? null;
            $bTravel = $b->travel_info[0] ?? null;

            if (!$aTravel)
                return 1;
            if (!$bTravel)
                return -1;

            if (!isset($aTravel['appointment_date']))
                return 1;
            if (!isset($bTravel['appointment_date']))
                return -1;

            $aDate = strtotime($aTravel['appointment_date']);
            $bDate = strtotime($bTravel['appointment_date']);

            return $aDate - $bDate;
        });

        return $finalResults;
    }
}