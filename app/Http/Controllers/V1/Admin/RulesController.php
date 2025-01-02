<?php

namespace App\Http\Controllers\V1\Admin;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Rules;
use App\Models\User;
use App\Models\UserBankDetails;
use App\Models\UserDocuments;
use Exception;
use Illuminate\Http\Request;



class RulesController extends Controller
{


    public function uploadRules(Request $request)
    {
        // Validate the file input
        $validated = $request->validate([
            'file' => 'required|mimes:pdf|max:2048', // File must be a PDF and not exceed 2MB
        ]);

        try {
            // Save the file to the server
            $file = $request->file('file');
            $filePath = Helper::saveImageToServer($file, '/uploads/rules/');

            // Check if a terms and conditions file already exists
            $termsAndConditions = Rules::first();

            if ($termsAndConditions) {
                // If a file exists, update the file URL and delete the old file
                $existingFileUrl = $termsAndConditions->file_url;

                // Remove the existing file from the server if it exists
                $existingFilePath = public_path(parse_url($existingFileUrl, PHP_URL_PATH));
                if (file_exists($existingFilePath)) {
                    unlink($existingFilePath);
                }

                // Update the record with the new file URL
                $termsAndConditions->file_url = $filePath;
                $termsAndConditions->save();
            } else {
                // If no record exists, create a new one
                $termsAndConditions = new Rules();
                $termsAndConditions->file_url = $filePath;
                $termsAndConditions->save();
            }

            return response()->json([
                'status_code' => 1,
                'message' => 'Rules file uploaded successfully.',
                'data' => [
                    'file_url' => $filePath,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Failed to upload file.',
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function fetchRules()
    {
        try {
            // Retrieve the latest terms and conditions file
            $termsAndConditions = Rules::latest('uploaded_at')->first();

            if (!$termsAndConditions) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'No Terms and Conditions file found.',
                    'data' => null,
                ], 200);
            }

            return response()->json([
                'status_code' => 1,
                'message' => 'Rules retrieved successfully.',
                'data' => [
                    'file_url' => $termsAndConditions->file_url,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Failed to fetch Rules.',
                'error' => $e->getMessage(),
            ], 200);
        }
    }
}
