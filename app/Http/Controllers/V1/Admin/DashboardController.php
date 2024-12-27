<?php

namespace App\Http\Controllers\V1\Admin;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Models\Matches;
use Exception;
use Illuminate\Http\Request;
use App\Models\Winner;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


class DashboardController extends Controller
{
    public function getDashboardData()
    {
        // Total number of events created
        $totalEventsCreated = Event::count();

        // Latest event winner's user name and event name
        $latestWinner = Winner::with(['user', 'event'])
            ->latest()
            ->first();
        $latestWinnerData = null;
        if ($latestWinner) {
            $latestWinnerData = [
                'user_name' => $latestWinner->user->name,
                'event_name' => $latestWinner->event->name,
            ];
        }

        // Total number of registered users with role "USER"
        // $totalUsers = User::where('role', 'USER')->count();
        $totalUsers = User::where('role', 'USER')
        ->where('is_verified', true)
        ->count();

        // Total number of events for today's date
        $today = Carbon::today()->toDateString();
        $totalEventsToday = Event::whereDate('go_live_date', $today)->count();

        // Total number of matches for today's date using date_time field
        $totalMatchesToday = Matches::whereDate('date_time', $today)->count();

        return response()->json([
            'status_code' => 1,
            'data' => [
                'latest_winner' => $latestWinnerData,
                'total_users' => $totalUsers,
                'total_events' => $totalEventsToday,
                'matches_today' => $totalMatchesToday,
                'amount_obtained' => 0,  // Assuming this is a placeholder
            ],
        ]);
    }



    public function getTotalEventsByType(Request $request)
    {
        // Validate the input
        $validated = $request->validate([
            'type' => 'required|string|in:day,week,month'
        ]);

        $type = $validated['type'];
        $today = Carbon::today();
        $totalEvents = 0;

        switch ($type) {
            case 'day':
                $totalEvents = Event::whereDate('go_live_date', $today)->count();
                break;

            case 'week':
                $startOfWeek = $today->copy()->startOfWeek();
                $endOfWeek = $today->copy()->endOfWeek();
                $totalEvents = Event::whereBetween('go_live_date', [$startOfWeek, $endOfWeek])->count();
                break;

            case 'month':
                $startOfMonth = $today->copy()->startOfMonth();
                $endOfMonth = $today->copy()->endOfMonth();
                $totalEvents = Event::whereBetween('go_live_date', [$startOfMonth, $endOfMonth])->count();
                break;

            default:
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Invalid type provided. Valid types are: day, week, month.'
                ]);
        }

        return response()->json([
            'status_code' => 1,
            'data' => [
                'total_events' => $totalEvents,
                'type' => $type
            ],

        ]);
    }
}
