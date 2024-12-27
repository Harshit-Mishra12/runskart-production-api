<?php

namespace App\Http\Controllers\V1\Admin;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class CricketExternalController extends Controller
{
    public function fetchMatches(Request $request)
    {
        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d', // Validates the date format as 'YYYY-MM-DD'
        ]);

        $apiKey = Helper::getApiKey();
        $cricScoreUrl = "https://api.cricapi.com/v1/cricScore?apikey={$apiKey}";
        $matchInfoUrl = "https://api.cricapi.com/v1/match_info?apikey={$apiKey}&id=";

        try {
            // Fetch matches list from cricScore API
            $response = Http::get($cricScoreUrl);

            if ($response->successful()) {
                // Fetch the data from the response
                $matchesData = $response->json()['data'];

                $inputDate = $request->input('date');
                $filteredMatches = [];

                // Get the current time in IST
                $currentIstTime = \Carbon\Carbon::now('Asia/Kolkata');

                // Filter matches by input date and current time using dateTimeGMT key
                if ($inputDate) {
                    $filteredMatches = array_filter($matchesData, function ($match) use ($inputDate, $currentIstTime) {
                        if (isset($match['dateTimeGMT'])) {
                            // Convert dateTimeGMT to IST using helper function
                            $matchDateIST = Helper::convertGmtToIst($match['dateTimeGMT']);
                            if ($matchDateIST) {
                                // Parse matchDateIST to compare both date and time
                                $matchDateTime = \Carbon\Carbon::parse($matchDateIST, 'Asia/Kolkata');
                                $matchDate = $matchDateTime->format('Y-m-d');
                                return $matchDate === $inputDate && $matchDateTime->greaterThanOrEqualTo($currentIstTime);
                            }
                        }
                        return false;
                    });
                } else {
                    $filteredMatches = $matchesData; // No date filter applied
                }

                $filteredMatches = array_values($filteredMatches); // Reindex the array

                // Retrieve detailed match information
                $matchDetails = [];
                foreach ($filteredMatches as $match) {
                    $matchId = $match['id'];
                    $infoResponse = Http::get($matchInfoUrl . $matchId);

                    if ($infoResponse->successful()) {
                        $matchInfo = $infoResponse->json()['data'];



                        // Convert dateTimeGMT to IST and add as datetime property
                        if (isset($match['dateTimeGMT'])) {
                            $matchInfo['dateTime'] = Helper::convertGmtToIst($match['dateTimeGMT']);
                        }

                        // Remove dateTimeGMT from matchInfo
                        unset($matchInfo['dateTimeGMT']);

                        $matchDetails[] = $matchInfo;
                    } else {
                        // Optionally log or handle failure to fetch match details
                    }
                }

                return response()->json(['status_code' => 1, 'data' => $matchDetails], 200);
            } else {
                return response()->json(['status_code' => 2, 'message' => 'Failed to fetch matches']);
            }
        } catch (\Exception $e) {
            return response()->json(['status_code' => 2, 'message' => 'An error occurred: ' . $e->getMessage()]);
        }
    }

    // public function fetchMatches(Request $request)
    // {
    //     $validated = $request->validate([
    //         'date' => 'nullable|date_format:Y-m-d', // Validates the date format as 'YYYY-MM-DD'
    //     ]);

    //     $apiKey = Helper::getApiKey();
    //     $cricScoreUrl = "https://api.cricapi.com/v1/cricScore?apikey={$apiKey}";
    //     $matchInfoUrl = "https://api.cricapi.com/v1/match_info?apikey={$apiKey}&id=";

    //     try {
    //         // Fetch matches list from cricScore API
    //         $response = Http::get($cricScoreUrl);

    //         if ($response->successful()) {
    //             // Fetch the data from the response
    //             $matchesData = $response->json()['data'];

    //             $inputDate = $request->input('date');
    //             $filteredMatches = [];

    //             // Get the current time in IST
    //             $currentIstTime = \Carbon\Carbon::now('Asia/Kolkata');

    //             // Filter matches by input date and current time using dateTimeGMT key
    //             if ($inputDate) {
    //                 $filteredMatches = array_filter($matchesData, function ($match) use ($inputDate, $currentIstTime) {
    //                     if (isset($match['dateTimeGMT'])) {
    //                         // Convert dateTimeGMT to IST using helper function
    //                         $matchDateIST = Helper::convertGmtToIst($match['dateTimeGMT']);
    //                         if ($matchDateIST) {
    //                             // Parse matchDateIST to compare both date and time
    //                             $matchDateTime = \Carbon\Carbon::parse($matchDateIST, 'Asia/Kolkata');
    //                             $matchDate = $matchDateTime->format('Y-m-d');
    //                             return $matchDate === $inputDate && $matchDateTime->greaterThanOrEqualTo($currentIstTime);
    //                         }
    //                     }
    //                     return false;
    //                 });
    //             } else {
    //                 $filteredMatches = $matchesData; // No date filter applied
    //             }

    //             $filteredMatches = array_values($filteredMatches); // Reindex the array

    //             // Retrieve detailed match information and filter based on the database match data
    //             $finalMatches = []; // Matches that pass all checks
    //             foreach ($filteredMatches as $match) {
    //                 $externalMatchId = $match['id']; // External match ID

    //                 // Check if the match exists in the local matches table
    //                 $matchId = DB::table('matches')
    //                     ->where('external_match_id', $externalMatchId) // Find the row with the matching external_match_id
    //                     ->value('id'); // Retrieve the match_id (id)


    //                 // Check if the match_id exists in the event_matches table
    //                 $eventMatchExists = DB::table('event_matches')
    //                     ->where('match_id', $matchId)
    //                     ->exists(); // Check if there is a row where match_id matches

    //                 // If the match_id is found in the event_matches table, skip this match
    //                 if ($eventMatchExists) {
    //                     continue; // Skip matches that already exist in event_matches
    //                 }

    //                 // Fetch detailed match information from the match info API
    //                 $infoResponse = Http::get($matchInfoUrl . $externalMatchId);

    //                 if ($infoResponse->successful()) {
    //                     $matchInfo = $infoResponse->json()['data'];

    //                     // Convert dateTimeGMT to IST and add as datetime property
    //                     if (isset($match['dateTimeGMT'])) {
    //                         $matchInfo['dateTime'] = Helper::convertGmtToIst($match['dateTimeGMT']);
    //                     }

    //                     // Remove dateTimeGMT from matchInfo
    //                     unset($matchInfo['dateTimeGMT']);

    //                     // Add the match information to the response list
    //                     $finalMatches[] = $matchInfo;
    //                 }
    //             }

    //             return response()->json(['status_code' => 1, 'data' => $finalMatches], 200);
    //         } else {
    //             return response()->json(['status_code' => 2, 'message' => 'Failed to fetch matches']);
    //         }
    //     } catch (\Exception $e) {
    //         return response()->json(['status_code' => 2, 'message' => 'An error occurred: ' . $e->getMessage()]);
    //     }
    // }


    // public function fetchMatches(Request $request)
    // {
    //     $validated = $request->validate([
    //         'date' => 'nullable|date_format:Y-m-d', // Validates the date format as 'YYYY-MM-DD'
    //     ]);

    //     $apiKey = Helper::getApiKey();
    //     $cricScoreUrl = "https://api.cricapi.com/v1/cricScore?apikey={$apiKey}";
    //     $matchInfoUrl = "https://api.cricapi.com/v1/match_info?apikey={$apiKey}&id=";

    //     try {
    //         // Fetch matches list from cricScore API
    //         $response = Http::get($cricScoreUrl);

    //         if ($response->successful()) {
    //             // Fetch the data from the response
    //             $matchesData = $response->json()['data'];

    //             $inputDate = $request->input('date');
    //             $filteredMatches = [];

    //             // Filter matches by input date using dateTimeGMT key
    //             if ($inputDate) {
    //                 $filteredMatches = array_filter($matchesData, function ($match) use ($inputDate) {
    //                     if (isset($match['dateTimeGMT'])) {
    //                         // Convert dateTimeGMT to Y-m-d format for comparison
    //                         $matchDate = \Carbon\Carbon::parse($match['dateTimeGMT'])->format('Y-m-d');
    //                         return $matchDate === $inputDate;
    //                     }
    //                     return false;
    //                 });
    //             } else {
    //                 $filteredMatches = $matchesData; // No date filter applied
    //             }

    //             $filteredMatches = array_values($filteredMatches); // Reindex the array

    //             // Retrieve detailed match information
    //             $matchDetails = [];
    //             foreach ($filteredMatches as $match) {
    //                 $matchId = $match['id'];
    //                 $infoResponse = Http::get($matchInfoUrl . $matchId);

    //                 if ($infoResponse->successful()) {
    //                     $matchInfo = $infoResponse->json()['data'];
    //                     $matchDetails[] = $matchInfo;
    //                 } else {
    //                     // Optionally log or handle failure to fetch match details
    //                 }
    //             }

    //             return response()->json(['status_code' => 1, 'data' => $matchDetails], 200);
    //         } else {
    //             return response()->json(['status_code' => 0, 'message' => 'Failed to fetch matches'], 500);
    //         }
    //     } catch (\Exception $e) {
    //         return response()->json(['status_code' => 0, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
    //     }
    // }
    public function getMatchSquad($id)
    {
        // Get the API key from a helper function or environment variable
        $apiKey = env('CRICAPI_KEY'); // You can use Helper::getApiKey() if it's defined

        // Fetch match squad data from the external API
        $response = Http::get("https://api.cricapi.com/v1/match_squad", [
            'apikey' => $apiKey,
            'id' => $id
        ]);

        // Check if the response is successful
        if ($response->successful()) {
            $data = $response->json();

            // Process and transform the data
            $result = $this->processData($data);

            return response()->json($result);
        }

        return response()->json(['error' => 'Failed to fetch data'], 500);
    }

    private function processData($data)
    {
        $processed = [];

        // Check if 'data' key exists in the response
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $team) {
                foreach ($team['players'] as $player) {
                    $role = $player['role'] ?: 'Unknown'; // Default to 'Unknown' if role is not set

                    // Initialize the array for the role if not already set
                    if (!isset($processed[$role])) {
                        $processed[$role] = [];
                    }

                    // Add player information to the appropriate role
                    $processed[$role][] = [
                        'playerId' => $player['id'],
                        'name' => $player['name'],
                        'battingStyle' => $player['battingStyle'],
                        'bowlingStyle' => $player['bowlingStyle'] ?? 'N/A',
                        'country' => $player['country'],
                        'playerImg' => $player['playerImg']
                    ];
                }
            }
        }

        return $processed;
    }
}
