<?php

namespace App\Http\Controllers\V1\Admin;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventMatch;
use App\Models\Matches;
use App\Models\UserTeam;
use App\Models\User;
use App\Models\TeamPlayer;
use App\Models\Team;
use App\Models\EventPrize;
use App\Models\MatchPlayer;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;


class EventsController extends Controller
{
    public function createEvent(Request $request)
    {
        // Validate the request
        $validatedData = $request->validate([
            'name' => 'required',
            'go_live_date' => 'required|date_format:Y-m-d',
            'team_size' => 'required|integer|min:1',
            'batsman_limit' => 'required|integer|min:0',
            'bowler_limit' => 'required|integer|min:0',
            'wicketkeeper_limit' => 'required|integer|min:0',
            'all_rounder_limit' => 'required|integer|min:0',
            'team_creation_cost' => 'required|numeric|min:0',
            'user_participation_limit' => 'required|integer|min:0',
            'winners_limit' => 'required|integer|min:1',
            'prizes' => 'required|array',
            'other_prizes' => 'required',
            'prizes.*.rank' => 'required|integer',
            'prizes.*.prize_amount' => 'required|numeric|min:0',
            'matches' => 'required|array',
            'team_limit_per_user' => 'required'
        ]);

        DB::beginTransaction();

        try {
            // Create the event
            $event = Event::create([
                'name' => $request->input('name'),
                'go_live_date' => $request->input('go_live_date'),
                'team_size' => $request->input('team_size'),
                'batsman_limit' => $request->input('batsman_limit'),
                'bowler_limit' => $request->input('bowler_limit'),
                'wicketkeeper_limit' => $request->input('wicketkeeper_limit'),
                'all_rounder_limit' => $request->input('all_rounder_limit'),
                'team_creation_cost' => $request->input('team_creation_cost'),
                'user_participation_limit' => $request->input('user_participation_limit'),
                'winners_limit' => $request->input('winners_limit'),
                'team_limit_per_user' => $request->input('team_limit_per_user'),
                'status' => 'CREATED'
            ]);


            // Handle prizes
            $prizes = $request->input('prizes');
            foreach ($prizes as $index => $prize) {
                if (isset($prize['rank']) && isset($prize['prize_amount'])) {
                    $rankFrom = $prize['rank'];
                    EventPrize::create([
                        'event_id' => $event->id,
                        'rank_from' => $rankFrom,
                        'rank_to' => $rankFrom,
                        'prize_amount' => $prize['prize_amount'],
                        'type' => 'top_rank'
                    ]);
                }
            }
            EventPrize::create([
                'event_id' => $event->id,
                'rank_from' => count($prizes) + 1,
                'rank_to' => $request->input('winners_limit'),
                'prize_amount' => $request->input('other_prizes'),
                'type' => 'other_rank'
            ]);
            $matches = $request->input('matches');
            $apiKey = Helper::getApiKey();

            $firstMatchTime = null; // To determine the earliest match dateTime

            foreach ($matches as $matchData) {
                // Create the match record
                $match = Matches::create([
                    'external_match_id' => $matchData['id'],
                    'team1' => $matchData['teams'][0],
                    'team2' => $matchData['teams'][1],
                    'team1_url' => $matchData['teamInfo'][0]["img"] ?? null,
                    'team2_url' => $matchData['teamInfo'][1]["img"] ?? null,
                    'date_time' => $matchData['dateTime'],
                    'venue' => $matchData['venue'],
                    'status' => $matchData['status'],
                    'is_squad_announced' => $matchData['hasSquad']
                ]);

                // Link the match to the event
                EventMatch::create([
                    'event_id' => $event->id,
                    'match_id' => $match->id
                ]);

                // Update the earliest match start time
                $matchDateTime = Carbon::parse($matchData['dateTime']);
                if (is_null($firstMatchTime) || $matchDateTime->lessThan($firstMatchTime)) {
                    $firstMatchTime = $matchDateTime;
                }

                // Fetch squad data using external_match_id
                $externalMatchId = $match->external_match_id;
                $response = Http::get("https://api.cricapi.com/v1/match_squad", [
                    'apikey' => $apiKey,
                    'id' => $externalMatchId
                ]);

                if ($response->successful()) {
                    $squadData = $response->json();
                    foreach ($squadData['data'] as $team) {
                        foreach ($team['players'] as $player) {
                            $roleString = strtolower(trim($player['role']));
                            $role = 'unknown';
                            if (stripos($roleString, 'wk-batsman') !== false) {
                                $role = 'wicketkeeper';
                            } elseif (stripos($roleString, 'batting allrounder') !== false || stripos($roleString, 'bowling allrounder') !== false) {
                                $role = 'allrounder';
                            } elseif (stripos($roleString, 'batsman') !== false && stripos($roleString, 'wk') === false) {
                                $role = 'batsman';
                            } elseif (stripos($roleString, 'bowler') !== false) {
                                $role = 'bowler';
                            }

                            MatchPlayer::create([
                                'match_id' => $match->id,
                                'event_id' => $event->id,
                                'external_player_id' => $player['id'],
                                'name' => $player['name'],
                                'role' => $role,
                                'country' => $player['country'],
                                'team' => $team['teamName'] ?? 'Unknown',
                                'image_url' => $player['playerImg'],
                                'status' => 'UNANNOUNCED'
                            ]);
                        }
                    }
                }
            }

            // Update the event's start time
            if ($firstMatchTime) {
                $event->update(['event_start_time' => $firstMatchTime]);
            }

            DB::commit();

            return response()->json([
                'status_code' => 1,
                'message' => 'Event and match squad created successfully',
                'data' => ['event' => $event],
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return response()->json([
                'status_code' => 2,
                'message' => 'Error creating event or fetching squad data',
                'error' => $e->getMessage()
            ]);
        }
    }

    // public function createEvent(Request $request)
    // {
    //     // Validate the request
    //     $validatedData = $request->validate([
    //         // 'name' => 'required|string|max:255|unique:events,name',
    //         'name' => 'required',
    //         'go_live_date' => 'required|date_format:Y-m-d',
    //         'team_size' => 'required|integer|min:1',
    //         'batsman_limit' => 'required|integer|min:0',
    //         'bowler_limit' => 'required|integer|min:0',
    //         'wicketkeeper_limit' => 'required|integer|min:0',
    //         'all_rounder_limit' => 'required|integer|min:0',
    //         'team_creation_cost' => 'required|numeric|min:0',
    //         'user_participation_limit' => 'required|integer|min:0',
    //         'winners_limit' => 'required|integer|min:1',
    //         'prizes' => 'required|array',
    //         'other_prizes' => 'required',
    //         'prizes.*.rank' => 'required|integer',
    //         'prizes.*.prize_amount' => 'required|numeric|min:0',
    //         'matches' => 'required|array',
    //         'team_limit_per_user' => 'required'
    //     ]);

    //     DB::beginTransaction();

    //     try {
    //         // Create the event
    //         $event = Event::create([
    //             'name' => $request->input('name'),
    //             'go_live_date' => $request->input('go_live_date'),
    //             'team_size' => $request->input('team_size'),
    //             'batsman_limit' => $request->input('batsman_limit'),
    //             'bowler_limit' => $request->input('bowler_limit'),
    //             'wicketkeeper_limit' => $request->input('wicketkeeper_limit'),
    //             'all_rounder_limit' => $request->input('all_rounder_limit'),
    //             'team_creation_cost' => $request->input('team_creation_cost'),
    //             'user_participation_limit' => $request->input('user_participation_limit'),
    //             'winners_limit' => $request->input('winners_limit'),
    //             'team_limit_per_user' => $request->input('team_limit_per_user'),
    //             'status' => 'CREATED'
    //         ]);

    //         // Handle prizes
    //         $prizes = $request->input('prizes');
    //         foreach ($prizes as $index => $prize) {
    //             if (isset($prize['rank']) && isset($prize['prize_amount'])) {
    //                 $rankFrom = $prize['rank'];
    //                 EventPrize::create([
    //                     'event_id' => $event->id,
    //                     'rank_from' => $rankFrom,
    //                     'rank_to' => $rankFrom,
    //                     'prize_amount' => $prize['prize_amount'],
    //                     'type' => 'top_rank'
    //                 ]);
    //             }
    //         }

    //         EventPrize::create([
    //             'event_id' => $event->id,
    //             'rank_from' => count($prizes) + 1,
    //             'rank_to' => $request->input('winners_limit'),
    //             'prize_amount' => $request->input('other_prizes'),
    //             'type' => 'other_rank'
    //         ]);

    //         // Add matches and process squad data
    //         $matches = $request->input('matches');
    //         $apiKey = Helper::getApiKey();

    //         foreach ($matches as $matchData) {

    //             $match = Matches::create([
    //                 'external_match_id' => $matchData['id'],
    //                 'team1' => $matchData['teams'][0],
    //                 'team2' => $matchData['teams'][1],
    //                 'team1_url' => isset($matchData['teamInfo'][0]["img"]) ? $matchData['teamInfo'][0]["img"] : null, // Check if 'teamInfo' exists
    //                 'team2_url' => isset($matchData['teamInfo'][1]["img"]) ? $matchData['teamInfo'][1]["img"] : null, // Check if 'teamInfo' exists
    //                 'date_time' => $matchData['dateTime'],
    //                 'venue' => $matchData['venue'],
    //                 'status' => $matchData['status'],
    //                 'is_squad_announced' => $matchData['hasSquad']
    //             ]);



    //             // Link the match to the event
    //             EventMatch::create([
    //                 'event_id' => $event->id,
    //                 'match_id' => $match->id
    //             ]);

    //             // Fetch squad data using external_match_id
    //             $externalMatchId = $match->external_match_id;
    //             $response = Http::get("https://api.cricapi.com/v1/match_squad", [
    //                 'apikey' => $apiKey,
    //                 'id' => $externalMatchId
    //             ]);

    //             if ($response->successful()) {
    //                 $squadData = $response->json();

    //                 // Process and store match player data
    //                 foreach ($squadData['data'] as $team) {
    //                     foreach ($team['players'] as $player) {
    //                         $roleString = strtolower(trim($player['role'])); // Ensure lowercase and trimmed string

    //                         // Check for specific role cases
    //                         if (stripos($roleString, 'wk-batsman') !== false) {
    //                             $role = 'wicketkeeper';
    //                         } elseif (stripos($roleString, 'batting allrounder') !== false || stripos($roleString, 'bowling allrounder') !== false) {
    //                             $role = 'allrounder';
    //                         } elseif (stripos($roleString, 'batsman') !== false && stripos($roleString, 'wk') === false) {
    //                             $role = 'batsman';
    //                         } elseif (stripos($roleString, 'bowler') !== false) {
    //                             $role = 'bowler';
    //                         } else {
    //                             $role = 'unknown'; // Default to unknown if no match found
    //                         }
    //                         MatchPlayer::create([
    //                             'match_id' => $match->id,
    //                             'event_id' => $event->id,
    //                             'external_player_id' => $player['id'],
    //                             'name' => $player['name'],
    //                             'role' =>   $role,
    //                             'country' => $player['country'],
    //                             'team' => isset($team['teamName']) ? $team['teamName'] : 'Unknown',
    //                             'image_url' => $player['playerImg'],
    //                             'status' => "UNANNOUNCED"
    //                         ]);
    //                     }
    //                 }
    //             }
    //         }

    //         DB::commit();

    //         return response()->json([
    //             'status_code' => 1,
    //             'message' => 'Event and match squad created successfully',
    //             'data' => ['event' => $event],
    //         ]);
    //     } catch (Exception $e) {
    //         DB::rollBack();

    //         // Handle exception
    //         return response()->json([
    //             'status_code' => 2,
    //             'message' => 'Error creating event or fetching squad data',
    //             'error' => $e->getMessage()
    //         ]);
    //     }
    // }

    public function updateEvent(Request $request)
    {
        // return $request->input('event_id');
        // Validate the request
        $request->validate([
            'event_id' => 'required|integer|exists:events,id',
            'name' => 'required|string|max:255',
            'team_size' => 'required|integer|min:1',
            'batsman_limit' => 'required|integer|min:0',
            'bowler_limit' => 'required|integer|min:0',
            'wicketkeeper_limit' => 'required|integer|min:0',
            'all_rounder_limit' => 'required|integer|min:0',
            'team_creation_cost' => 'required|numeric|min:0',
            'user_participation_limit' => 'required|integer|min:0',
            'winners_limit' => 'required|integer|min:1',
            'prizes' => 'required|array',
            'other_prizes' => 'required|numeric|min:0',
            'team_limit_per_user' => 'required'
            // 'prizes.*.rank' => 'required|integer',
            // 'prizes.*.prize_amount' => 'required|numeric|min:0',
        ]);


        $eventId = $request->input('event_id');

        // Find the event by ID
        $event = Event::find($eventId);

        if (!$event) {
            return response()->json(['status_code' => 0, 'message' => 'Event not found'], 404);
        }

        DB::beginTransaction();

        try {
            // Update the event fields
            $event->update([
                'name' => $request->input('name'),
                'team_size' => $request->input('team_size'),
                'batsman_limit' => $request->input('batsman_limit'),
                'bowler_limit' => $request->input('bowler_limit'),
                'wicketkeeper_limit' => $request->input('wicketkeeper_limit'),
                'all_rounder_limit' => $request->input('all_rounder_limit'),
                'team_creation_cost' => $request->input('team_creation_cost'),
                'user_participation_limit' => $request->input('user_participation_limit'),
                'team_limit_per_user' => $request->input('team_limit_per_user'),
                'winners_limit' => $request->input('winners_limit'),
                'status' => $event->status,  // Keep the current status
            ]);

            // Handle prizes update
            $prizes = $request->input('prizes');

            // Remove existing prizes for the event
            EventPrize::where('event_id', $event->id)->delete();

            // Add the updated prizes
            foreach ($prizes as $prize) {
                if (isset($prize['rank_from']) && isset($prize['prize_amount'])) {
                    // $rankFrom = $prize['rank'];
                    EventPrize::create([
                        'event_id' => $event->id,
                        'rank_from' => $prize['rank_from'],
                        'rank_to' => $prize['rank_to'],
                        'prize_amount' => $prize['prize_amount']
                    ]);
                }
            }

            // Add other prize details
            EventPrize::create([
                'event_id' => $event->id,
                'rank_from' => count($prizes) + 1,
                'rank_to' => $request->input('winners_limit'),
                'prize_amount' => $request->input('other_prizes')
            ]);

            DB::commit();

            return response()->json([
                'status_code' => 1,
                'message' => 'Event updated successfully',
                'data' => ['event' => $event],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            // Handle exception
            return response()->json([
                'status_code' => 2,
                'message' => 'Error updating event',
                'error' => $e->getMessage()
            ]);
        }
    }


    //scenrios
    public function getEventList(Request $request)
    {
        // Validate the input
        $validated = $request->validate([
            'per_page' => 'integer|min:1',
            'page' => 'integer|min:1',
            'date' => 'date|nullable|date_format:Y-m-d',
            'go_live_date' => 'boolean|nullable', // Add this line
        ]);

        // Define the number of items per page, default is 10
        $perPage = $validated['per_page'] ?? 10;

        // Base query for events
        $query = Event::query();

        // Apply date filter if provided
        if (!empty($validated['date'])) {
            $query->whereDate('go_live_date', $validated['date']);
        }

        // If go_live_date is true, sort by go_live_date
        if (isset($validated['go_live_date']) && $validated['go_live_date']) {
            $query->where('status', '!=', 'COMPLETED');
            $query->orderBy('go_live_date', 'asc'); // Nearest go_live_date first
        } else {
            // Default order by created_at if go_live_date is not specified
            $query->orderBy('created_at', 'desc');
        }

        // Paginate the events
        $events = $query->select('id', 'name', 'event_start_time', 'go_live_date', 'team_creation_cost', 'user_participation_limit', 'status', 'activate_status', 'created_at')
            ->paginate($perPage);

        // Extract event IDs from the paginated results
        $eventIds = $events->pluck('id')->toArray();

        // Get the current date and time
        $currentDateTime = now();

        // Format the response data
        $eventsList = $events->map(function ($event) use ($currentDateTime) {
            // Determine the event status based on matches
            $matchDateTimes = Matches::whereIn('id', function ($query) use ($event) {
                $query->select('match_id')
                    ->from('event_matches')
                    ->where('event_id', $event->id);
            })->pluck('external_match_id');

            $teamCount = Team::where('event_id', $event->id)->count();
            $matchStarted = false; // Assuming logic for determining if a match has started
            $allMatchesEnded = true; // Assuming logic for determining if all matches have ended

            $eventStatus = 'UPCOMING'; // Default status

            // Determine the correct status based on match conditions
            if ($matchStarted && $allMatchesEnded) {
                $eventStatus = 'COMPLETED';
            } elseif ($matchStarted) {
                $eventStatus = 'LIVE';
            } elseif ($event->go_live_date < $currentDateTime->toDateString() && $allMatchesEnded) {
                $eventStatus = 'COMPLETED';
            }
            // Extract time from event_start_time and combine with go_live_date
            $eventStartTime = $event->event_start_time ? Carbon::parse($event->event_start_time)->format('H:i:s') : null;
            $goLiveDateTime = $event->go_live_date;
            if ($eventStartTime) {
                $goLiveDateTime .= ' ' . $eventStartTime;
            }
            return [
                'id' => $event->id,
                'event_name' => $event->name,
                'go_live_date' => $goLiveDateTime,
                // 'go_live_date_time' => $goLiveDateTime,
                'team_creation_cost' => $event->team_creation_cost,
                'occupancy' => $teamCount ?? 0, // Get the count or default to 0
                'user_participation_limit' => $event->user_participation_limit,
                'status' => $event->status,
                'activate_status' => $event->activate_status,
            ];
        });

        // Get the total number of events (without pagination)
        $totalEvents = Event::count();

        return response()->json([
            'status_code' => 1,
            'message' => 'Events retrieved successfully',
            'data' => [
                'eventsList' => $eventsList,
                'totalEvents' => $totalEvents,
            ],
            'current_page' => $events->currentPage(),
            'last_page' => $events->lastPage(),
            'per_page' => $events->perPage(),
            'total' => $events->total(),
        ]);
    }

    // public function getEventList(Request $request)
    // {
    //     // Validate the input
    //     $validated = $request->validate([
    //         'per_page' => 'integer|min:1',
    //         'page' => 'integer|min:1',
    //         'date' => 'date|nullable|date_format:Y-m-d',
    //     ]);

    //     // Define the number of items per page, default is 10
    //     $perPage = $validated['per_page'] ?? 10;

    //     // Base query for events
    //     $query = Event::query();

    //     // Apply date filter if provided
    //     if (!empty($validated['date'])) {
    //         $query->whereDate('go_live_date', $validated['date']);
    //     }

    //     // Order by created_at to ensure events are sorted based on creation date
    //     $query->orderBy('created_at', 'desc');

    //     // Paginate the events
    //     $events = $query->select('id', 'name', 'go_live_date', 'team_creation_cost', 'user_participation_limit', 'status', 'activate_status', 'created_at')
    //         ->paginate($perPage);

    //     // Extract event IDs from the paginated results
    //     $eventIds = $events->pluck('id')->toArray();




    //     // Get the current date and time
    //     $currentDateTime = now();

    //     // Format the response data
    //     $eventsList = $events->map(function ($event) use ($currentDateTime) {
    //         // Determine the event status based on matches
    //         $matchDateTimes = Matches::whereIn('id', function ($query) use ($event) {
    //             $query->select('match_id')
    //                 ->from('event_matches')
    //                 ->where('event_id', $event->id);
    //         })->pluck('external_match_id');

    //         $teamCount = Team::where('event_id', $event->id)->count();
    //         $matchStarted = false;
    //         $allMatchesEnded = true;



    //         $eventStatus = 'UPCOMING'; // Default status

    //         // Determine the correct status based on match conditions
    //         if ($matchStarted && $allMatchesEnded) {
    //             $eventStatus = 'COMPLETED';
    //         } elseif ($matchStarted) {
    //             $eventStatus = 'LIVE';
    //         } elseif ($event->go_live_date < $currentDateTime->toDateString() && $allMatchesEnded) {
    //             $eventStatus = 'COMPLETED';
    //         }

    //         return [
    //             'id' => $event->id,
    //             'event_name' => $event->name,
    //             'go_live_date' => $event->go_live_date,
    //             'team_creation_cost' => $event->team_creation_cost,
    //             'occupancy' => $teamCount ?? 0, // Get the count or default to 0
    //             'user_participation_limit' => $event->user_participation_limit,
    //             'status' => $event->status,
    //             'activate_status' => $event->activate_status
    //         ];
    //     });

    //     // Get the total number of events (without pagination)
    //     $totalEvents = Event::count();

    //     return response()->json([
    //         'status_code' => 1,
    //         'message' => 'Events retrieved successfully',
    //         'data' => [
    //             'eventsList' => $eventsList,
    //             'totalEvents' => $totalEvents
    //         ],
    //         'current_page' => $events->currentPage(),
    //         'last_page' => $events->lastPage(),
    //         'per_page' => $events->perPage(),
    //         'total' => $events->total(),
    //     ]);
    // }


    private function fetchMatchStatus($externalMatchId)
    {
        // Use the helper function to get the API key
        $apiKey = Helper::getApiKey();

        // Fetch match status from the external API
        $apiUrl = "https://api.cricapi.com/v1/match_info?apikey={$apiKey}&id={$externalMatchId}";
        $response = file_get_contents($apiUrl);

        if ($response === false) {
            // If the API call fails, return default values
            return ['matchStarted' => false, 'matchEnded' => true];
        }

        $status = json_decode($response, true);

        // Check if the response data and required fields are set correctly
        if (isset($status['data'])) {
            return [
                'matchStarted' => $status['data']['matchStarted'] ?? false,
                'matchEnded' => $status['data']['matchEnded'] ?? true
            ];
        }

        // Return default values if the response data is not structured as expected
        return ['matchStarted' => false, 'matchEnded' => true];
    }

    public function activateEventStatus($eventId)
    {
        // Attempt to find the event by its ID
        $event = Event::find($eventId);

        // Check if the event was found
        if (!$event) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Event not found',
            ]);
        }

        // Retrieve all matches for this event
        $allMatchesAnnounced = EventMatch::where('event_id', $eventId)
            ->whereHas('match', function ($query) {
                $query->where('is_squad_announced', true);
            })
            ->count() === EventMatch::where('event_id', $eventId)->count();

        // Check if all matches for this event have is_squad_announced set to true
        if (!$allMatchesAnnounced) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Event cannot be activated. Not all matches have squads announced.',
            ]);
        }

        // Update the activate_status to 'ACTIVE'
        $event->activate_status = 'ACTIVE';

        // Save the changes to the database
        if ($event->save()) {
            return response()->json([
                'status_code' => 1,
                'message' => 'Event status updated to ACTIVE successfully',
            ]);
        } else {
            // Return an error response if the save failed
            return response()->json([
                'status_code' => 2,
                'message' => 'Failed to update event status',
            ]);
        }
    }

    // public function activateEventStatus($eventId)
    // {
    //     // Attempt to find the event by its ID
    //     $event = Event::find($eventId);

    //     // Check if the event was found
    //     if (!$event) {
    //         return response()->json([
    //             'status_code' => 2,
    //             'message' => 'Event not found',
    //         ]);
    //     }

    //     // Update the activate_status to 'ACTIVE'
    //     $event->activate_status = 'ACTIVE';

    //     // Save the changes to the database
    //     if ($event->save()) {
    //         return response()->json([
    //             'status_code' => 1,
    //             'message' => 'Event status updated to ACTIVE successfully',
    //         ]);
    //     } else {
    //         // Return an error response if the save failed
    //         return response()->json([
    //             'status_code' => 2,
    //             'message' => 'Failed to update event status',
    //         ]);
    //     }
    // }


    public function getEventDetails($eventId)
    {
        DB::beginTransaction();
        try {
            // Fetch event details
            $event = Event::find($eventId);

            if (!$event) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Event not found'
                ]);
            }

            // Fetch event matches and their details
            $eventMatches = EventMatch::where('event_id', $eventId)
                ->with('match') // Assuming you have a relation defined in EventMatch model for matches
                ->get();

            // Count the number of matches for this event
            $matchesCount = $eventMatches->count();
            // Loop through each match to fetch its status using external_match_id
            foreach ($eventMatches as $eventMatch) {
                $matchStatus = $this->fetchMatchStatus($eventMatch->match->external_match_id); // Fetch status using external_match_id

                // Update match object with fetched status
                $eventMatch->match->matchStarted = $matchStatus['matchStarted'];
                $eventMatch->match->matchEnded = $matchStatus['matchEnded'];
            }
            // Fetch event prizes
            $prizes = EventPrize::where('event_id', $eventId)
                ->orderBy('rank_from')
                ->get();

            // Map event matches to include necessary details
            $eventMatchesDetails = $eventMatches->map(function ($eventMatch) {
                return [
                    'match_id' => $eventMatch->match_id,
                    'team1' => $eventMatch->match->team1,
                    'team2' => $eventMatch->match->team2,
                    'date_time' => $eventMatch->match->date_time,
                    'venue' => $eventMatch->match->venue,
                    'status' => $eventMatch->match->status,
                    // Add other match details if needed
                ];
            });

            $occupancyCount = Team::where('event_id', $eventId)->count();
            DB::commit();

            return response()->json([
                'status_code' => 1,
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'go_live_date' => $event->go_live_date,
                        'team_size' => $event->team_size,
                        'batsman_limit' => $event->batsman_limit,
                        'bowler_limit' => $event->bowler_limit,
                        'all_rounder_limit' => $event->all_rounder_limit,
                        'team_creation_cost' => $event->team_creation_cost,
                        'user_participation_limit' => $event->user_participation_limit,
                        'wicketkeeper_limit' => $event->wicketkeeper_limit,
                        'winners_limit' => $event->winners_limit,
                        'matches_count' => $matchesCount,
                        'status' =>  $event->status,  // Updated status
                        'activate_status' => $event->activate_status,
                        'team_limit_per_user' => $event->team_limit_per_user
                    ],
                    'occupancy' => $occupancyCount,
                    'prizes' => $prizes,
                    'matches' => $eventMatchesDetails,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status_code' => 2,
                'message' => 'Error fetching event details',
                'error' => $e->getMessage()
            ]);
        }
    }



    public function deleteEvent($eventId)
    {
        DB::beginTransaction();

        try {
            // Find the event
            $event = Event::findOrFail($eventId);

            // Update the event status to "CANCELLED"
            $event->status = 'CANCELLED';
            $event->save();

            // Fetch event matches and their details
            $eventMatches = EventMatch::where('event_id', $eventId)
                ->with('match')
                ->get();

            // Count the number of matches for this event
            $matchesCount = $eventMatches->count();

            // Fetch event prizes
            $prizes = EventPrize::where('event_id', $eventId)
                ->orderBy('rank_from')
                ->get();

            // Fetch event matches and associated match data
            $eventMatches = EventMatch::where('event_id', $eventId)
                ->with('match')
                ->get()
                ->map(function ($eventMatch) {
                    return [
                        'match_id' => $eventMatch->match_id,
                        'team1' => $eventMatch->match->team1,
                        'team2' => $eventMatch->match->team2,
                        'date_time' => $eventMatch->match->date_time,
                        'venue' => $eventMatch->match->venue,
                        'status' => $eventMatch->match->status,
                        // Add other match details if needed
                    ];
                });

            // Count the number of users participating in this event
            $occupancyCount = UserTeam::where('event_id', $eventId)->count();

            DB::commit();

            return response()->json([
                'status_code' => 1,
                'message' => 'Event marked as cancelled successfully',
                'data' => [
                    'event' => [
                        'id' => $event->id,
                        'name' => $event->name,
                        'go_live_date' => $event->go_live_date,
                        'team_size' => $event->team_size,
                        'batsman_limit' => $event->batsman_limit,
                        'bowler_limit' => $event->bowler_limit,
                        'all_rounder_limit' => $event->all_rounder_limit,
                        'team_creation_cost' => $event->team_creation_cost,
                        'user_participation_limit' => $event->user_participation_limit,
                        'winners_limit' => $event->winners_limit,
                        'matches_count' => $matchesCount,
                        'status' => $event->status,  // Now 'CANCELLED'
                    ],
                    'occupancy' => $occupancyCount,
                    'prizes' => $prizes,
                    'matches' => $eventMatches,
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status_code' => 2,
                'message' => 'Error cancelling event',
                'error' => $e->getMessage()
            ]);
        }
    }

    ///



    public function getPlayersList($match_id)
    {
        // Fetch all players from the MatchPlayer table based on the match_id
        $players = MatchPlayer::where('match_id', $match_id)->get();

        // Check if players are found
        if ($players->isEmpty()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'No players found for the given match',
            ]);
        }

        // Initialize an array to store player info
        $playersInfo = [];

        // CricAPI key

        $apiKey = Helper::getApiKey();
        foreach ($players as $player) {
            $playersInfo[] = [
                'id' => $player->id,
                'match_id' => $player->match_id,
                'external_player_id' => $player->external_player_id,
                'playing_status' => $player->status,
                'team' => $player->team,
                'player_name' => $player->name,
                // 'player_details' => [],
                // 'player_details' => $response['data'],

            ];
        }
        // Loop through each player and fetch their information from the external API
        // foreach ($players as $player) {
        //     try {
        //         // Make an API call using the external_player_id
        //         $response = Http::get('https://api.cricapi.com/v1/players_info', [
        //             'apikey' => $apiKey,
        //             'id' => $player->external_player_id,
        //         ]);

        //         // Check if the API call was successful
        //         if ($response->successful() && isset($response['data'])) {
        //             // Append the fetched player info to the array
        //             $playersInfo[] = [
        //                 'id' => $player->id,
        //                 'match_id' => $player->match_id,
        //                 'external_player_id' => $player->external_player_id,
        //                 'playing_status' => $player->status,
        //                 'team' => $player->team,
        //                 'player_name' => $player->name,
        //                 'player_details' => $response['data'],
        //                 // 'player_details' => $response['data'],

        //             ];
        //         } else {
        //             // Append error info if API call fails or data is not present
        //             $playersInfo[] = [
        //                 'id' => $player->id,
        //                 'match_id' => $player->match_id,
        //                 'external_player_id' => $player->external_player_id,
        //                 'playing_status' => $player->status,
        //                 'team' => $player->team,
        //                 'player_name' => $player->name,
        //                 'player_details' => 'Player info not found or API error',
        //             ];
        //         }
        //     } catch (\Exception $e) {
        //         // Handle exception during the API call
        //         $playersInfo[] = [
        //             'player_id' => $player->player_id,
        //             'match_id' => $player->match_id,
        //             'external_player_id' => $player->external_player_id,
        //             'playing_status' => $player->status,
        //             'player_details' => 'Error fetching player info: ' . $e->getMessage(),
        //         ];
        //     }
        // }

        // Return the player information as a JSON response
        return response()->json([
            'status_code' => 1,
            'message' => 'Player information fetched successfully',
            'data' => $playersInfo,
        ]);
    }

    public function updatePlayingStatus(Request $request)
    {
        // Validate the incoming request, ensuring it's an array of objects with match_player_id and playing_status
        $validatedData = $request->validate([
            '*.match_player_id' => 'required|integer|exists:match_players,id', // Ensure each match_player_id exists
            '*.playing_status' => 'required|string|max:255', // Playing status is required for each item
        ]);

        try {
            // Loop through the validated data to update each MatchPlayer entry
            foreach ($validatedData as $data) {
                // Find the MatchPlayer record by match_player_id (id field in match_players table)
                $matchPlayer = MatchPlayer::findOrFail($data['match_player_id']);

                // Update the playing status
                $matchPlayer->update([
                    'status' => $data['playing_status'],
                ]);
            }

            // Return success response
            return response()->json([
                'status_code' => 1,
                'message' => 'Player statuses updated successfully',
            ]);
        } catch (\Exception $e) {
            // Handle exceptions
            return response()->json([
                'status_code' => 2,
                'message' => 'Error updating player statuses',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function createRandomUserTeam(Request $request)
    {
        // Validate the input array
        $validatedData = $request->validate([
            'userData' => 'required|array|min:1',
            'userData.*.user_id' => 'required|exists:users,id',  // Ensure user exists
            'userData.*.captainId' => 'required|exists:match_players,id', // Ensure captain exists
            'userData.*.playerIds' => 'required|array|min:1',
            'userData.*.playerIds.*' => 'exists:match_players,id', // Ensure all players exist
            'userData.*.event_id' => 'required|exists:events,id', // Ensure event exists
        ]);

        $userDataArray = $validatedData['userData'];

        DB::beginTransaction();

        try {
            $createdTeams = []; // To store details of created teams

            foreach ($userDataArray as $userData) {
                $userId = $userData['user_id'];
                $eventId = $userData['event_id'];
                $captainId = $userData['captainId'];
                $playerIds = $userData['playerIds'];

                // Fetch the event and check the team creation limit per user
                $event = Event::find($eventId);
                $teamLimitPerUser = $event->team_limit_per_user;

                // Count the existing teams for the user in this event
                $userTeamCount = Team::where('user_id', $userId)
                    ->where('event_id', $eventId)
                    ->count();

                if ($userTeamCount >= $teamLimitPerUser) {
                    // If the user has already created the maximum allowed teams, skip this user
                    continue;
                }

                // Create the team for the user
                $team = Team::create([
                    'user_id' => $userId,
                    'event_id' => $eventId,
                    'captain_match_player_id' => $captainId,
                    'status' => 'active',  // Set team status to active
                    'name' => 'Team ' . Str::random(5),
                    'points_scored' => 0,  // Default points scored
                ]);

                // Attach players to the team
                foreach ($playerIds as $playerId) {
                    TeamPlayer::create([
                        'team_id' => $team->id,
                        'match_player_id' => $playerId,
                    ]);
                }

                // Add created team details to the response array
                $createdTeams[] = [
                    'team_id' => $team->id,
                    'user_id' => $userId,
                    'event_id' => $eventId,
                    'captain_match_player_id' => $captainId,
                    'players' => $playerIds,
                ];
            }

            DB::commit();

            return response()->json([
                'status_code' => 1,
                'message' => 'Teams and players created successfully',
                'created_teams' => $createdTeams,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'status_code' => 2,
                'message' => 'Failed to create teams or players',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function randomUserData(Request $request)
    {
        // Validate the input
        $validatedData = $request->validate([
            'event_id' => 'required|exists:events,id', // Ensure event exists
        ]);

        $eventId = $validatedData['event_id'];

        // Find the event and its team limit per user
        $event = Event::find($eventId);
        $teamLimitPerUser = $event->team_limit_per_user;

        // Retrieve all users with role RANDOMUSER
        $randomUsers = User::where('role', 'RANDOMUSER')->get();

        // Create a response with user data and their remaining team creation limit
        $userData = $randomUsers->map(function ($user) use ($eventId, $teamLimitPerUser) {
            // Count how many teams this user has created for the given event
            $userTeams = Team::where('user_id', $user->id)
                ->where('event_id', $eventId)
                ->get();

            $userTeamCount = $userTeams->count();

            // Calculate the remaining team creation limit
            $remainingTeams = $teamLimitPerUser - $userTeamCount;

            // Extract captain_match_player_ids from the user's teams
            $captainIds = $userTeams->pluck('captain_match_player_id')->toArray();

            return [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                'team_created' => $userTeamCount,
                'remaining_teams' => max($remainingTeams, 0), // Ensure the remaining count is not negative
                'captain_match_player_ids' => $captainIds, // Array of captain_match_player_ids
            ];
        });

        // Return the response
        return response()->json([
            'status_code' => 1,
            'message' => 'Random users data fetched successfully',
            'users' => $userData,
        ]);
    }

    public function getTeamsByEvent($event_id)
    {
        // Fetch teams for the given event_id including user info and rank
        $teams = Team::where('event_id', $event_id)
            ->with(['user:id,name', 'userTransaction' => function ($query) {
                $query->select('team_id', 'amount', 'transaction_id')
                    ->where('transaction_type', 'credit'); // Filter for credit transactions only
            }])
            ->get();

        // Check if any teams are found
        if ($teams->isEmpty()) {
            return response()->json([
                'status_code' => 2,
                'message' => 'No teams found for this event.',
            ]);
        }

        // Map teams with additional info
        $teams = $teams->map(function ($team) {
            // Check if the team has a user transaction for prize amount
            $prize = $team->userTransaction ? $team->userTransaction->amount : 0;
            $transactionId = $team->userTransaction ? $team->userTransaction->transaction_id : null;

            return [
                'team_id' => $team->id,
                'team_name' => $team->name,
                'user_name' => $team->user->name,
                'rank' => $team->rank,
                'prize_amount' => $prize,
                'transaction_id' => $transactionId,
            ];
        });

        // Return the list of teams with their details
        return response()->json([
            'status_code' => 1,
            'message' => 'Teams and players retrieved successfully.',
            'teams' => $teams,
        ]);
    }
}
