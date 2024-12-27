<?php

namespace App\Http\Controllers\V1;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionDetail;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        // Validate the input to check for either email or mobile_number and password
        $request->validate([
            'email_or_mobile' => 'required', // Accepts either email or mobile number
            'password' => 'required',
        ]);

        // Determine if input is an email or mobile number
        $isEmail = filter_var($request->input('email_or_mobile'), FILTER_VALIDATE_EMAIL);
        $user = $isEmail
            ? User::where('email', $request->input('email_or_mobile'))->first()
            : User::where('mobile_number', $request->input('email_or_mobile'))->first();

        // Check if user exists
        if ($user) {
            // Check if the user is verified
            if ($user->is_verified) {
                // Verify the password
                if (Hash::check($request->input('password'), $user->password)) {
                    // Generate API token for the user
                    $token = $user->createToken('api-token')->plainTextToken;
                    return response()->json([
                        'status_code' => 1,
                        'data' => [
                            'user' => $user,
                            'token' => $token,
                        ],
                        'message' => 'Login successful.',
                    ]);
                } else {
                    return response()->json([
                        'status_code' => 2,
                        'data' => [],
                        'message' => 'Incorrect password.',
                    ]);
                }
            } else {
                return response()->json([
                    'status_code' => 2,
                    'data' => [],
                    'message' => 'Account not verified. Please complete registration first.',
                ]);
            }
        } else {
            return response()->json([
                'status_code' => 2,
                'data' => [],
                'message' => 'Account not registered.',
            ]);
        }
    }

    // public function login(Request $request)
    // {

    //     $request->validate([
    //         'email' => 'required|email',
    //         'password' => 'required',
    //     ]);

    //     $user = User::where('email', $request->email)->first();
    //     if ($user) {
    //         if ($user->is_verified) {
    //             if ($user && Hash::check($request->input('password'), $user->password)) {
    //                 $token = $user->createToken('api-token')->plainTextToken;
    //                 return response()->json(['status_code' => 1, 'data' => ['user' => $user, 'token' => $token], 'message' => 'Login successfull.']);
    //             } else {
    //                 return response()->json(['status_code' => 2, 'data' => [], 'message' => 'Incorrect password.']);
    //             }
    //         } else {
    //             return response()->json(['status_code' => 2, 'data' => [], 'message' => 'Account not verified. Please goto register first']);
    //         }
    //     } else {
    //         return response()->json(['status_code' => 2, 'data' => [], 'message' => 'Account not registered']);
    //     }
    // }

    public function register(Request $request)
    {
        Log::info('register test:', [$request->getContent()]);

        // Manually check for unique email and mobile_number only for verified users
        $request->validate([
            'name' => 'required',
            'email' => 'required|email',
            'mobile_number' => 'required',
            'password' => 'required|min:6',
            'dob' => 'required|date_format:Y-m-d',
            'profile_picture' => 'required|file|mimes:jpeg,jpg,png,pdf|max:2048',
            'documents_type' => 'nullable|array', // Optional
            'documents_type.*' => 'nullable|string', // Optional
            'documents_picture' => 'nullable|array', // Optional
            'documents_picture.*' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:2048',
        ]);

        $otp = mt_rand(1000, 9999); // Generate OTP

        // Check if the user exists with the same email or mobile number
        $existingUser = User::where(function ($query) use ($request) {
            $query->where('email', $request->email)
                ->orWhere('mobile_number', $request->mobile_number);
        })->first();

        // If the user exists and is verified, return an error
        if ($existingUser && $existingUser->is_verified) {
            return response()->json(['status_code' => 2, 'message' => 'User with this email or mobile number is already verified.']);
        }

        $profilePicturePath = '';

        // If the user exists but is not verified, update the record
        if ($existingUser && !$existingUser->is_verified) {
            // Handle profile picture upload
            if ($request->hasFile('profile_picture')) {
                $file = $request->file('profile_picture');
                $profilePicturePath = Helper::saveImageToServer($file, 'uploads/profile/');
            }

            // Update existing user details
            $existingUser->name = $request->input('name');
            $existingUser->email = $request->input('email');
            $existingUser->role = 'USER';
            $existingUser->dob = $request->input('dob');
            $existingUser->mobile_number = $request->input('mobile_number');
            $existingUser->password = bcrypt($request->input('password'));
            $existingUser->otp = $otp;
            $existingUser->profile_picture = $profilePicturePath;
            $existingUser->status = 'INACTIVE';
            $existingUser->save();

            $user = $existingUser;
        } else {
            // Handle profile picture upload for new user
            if ($request->hasFile('profile_picture')) {
                $file = $request->file('profile_picture');
                $profilePicturePath = Helper::saveImageToServer($file, 'uploads/profile/');
            }

            // Create a new user
            $user = new User();
            $user->name = $request->input('name');
            $user->email = $request->input('email');
            $user->role = 'USER';
            $user->dob = $request->input('dob');
            $user->mobile_number = $request->input('mobile_number');
            $user->password = bcrypt($request->input('password'));
            $user->otp = $otp;
            $user->profile_picture = $profilePicturePath;
            $user->status = 'INACTIVE';
            $user->doc_status = 'PENDING';
            $user->save();
        }

        // Send OTP via SMS
        $this->sendOtpSMS($user->mobile_number, $otp);

        // Handle user's documents upload
        $documentTypes = $request->input('documents_type', []); // Default to an empty array
        $documentPictures = $request->file('documents_picture', []); // Default to an empty array


        if (count($documentTypes) != count($documentPictures)) {
            return response()->json(['status_code' => 2, 'message' => 'Mismatch between document types and pictures.']);
        }

        foreach ($documentPictures as $index => $documentFile) {
            $docType = $documentTypes[$index];

            if ($documentFile instanceof \Illuminate\Http\UploadedFile) {
                // Save the document file
                $docUrlPath = Helper::saveImageToServer($documentFile, 'uploads/documents/');

                // Create document record
                $user->documents()->create([
                    'doc_type' => $docType,
                    'doc_url' => $docUrlPath
                ]);
            } else {
                return response()->json(['status_code' => 2, 'message' => 'Invalid document file.']);
            }
        }

        // Send verification email
        $data = [
            'name' => $user->name,
            'otp' => $user->otp
        ];
        $body = view('email.otp_verification', $data)->render();
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $subject = 'Verify your email';
        Helper::sendEmail($user->email, $subject, $body, $headers);

        return response()->json([
            'status_code' => 1,
            'data' => ['id' => $user->id],
            'message' => 'User registered successfully. Please verify your email.',
            'test_otp' => $otp
        ]);
    }





    public function register2(Request $request)
    {


        return response()->json(['status_code' => 1, 'message' => 'test register api.']);
    }
    private function sendOtpSMS($mobileNumber, $otp)
    {
        $fields = array(
            "message" => "Your OTP for registration is: $otp",
            "language" => "english",
            "route" => "q",
            "numbers" => $mobileNumber,
        );

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://www.fast2sms.com/dev/bulkV2",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => array(
                "authorization: 1A5KFGtiU27gVQfnch8oZsjpauSBxvY0blTCDedJXHEk9ILPOmLiUSjEIoOgtM03yG1XZQHrWpsTucCB", // Your API key
                "accept: */*",
                "cache-control: no-cache",
                "content-type: application/json"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        return json_decode($response, true);
    }


    public function verifyUser(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'otp' => 'required'
        ]);

        $user = User::find($request->input('id'));
        if (!$user || $user->is_verified == 1) {
            return response()->json(['status_code' => 2, 'data' => [], 'message' => 'User not found']);
        }
        if ($user->otp == $request->input('otp')) {

            $user->update([
                'is_verified' => true,
                'otp' => '',
                'status' => 'ACTIVE'
            ]);
            $token = $user->createToken('api-token')->plainTextToken;
            // $this->createSubscription($user->id);
            return response()->json(['status_code' => 1, 'data' => ['user' => $user, 'token' => $token], 'message' => 'User verified successfully']);
        }
        return response()->json(['status_code' => 2, 'data' => [], 'message' => 'Invalid Otp']);
    }
    private function createSubscription($user_id)
    {
        $subscriptionDetail = new SubscriptionDetail();
        $subscriptionDetail->user_id = $user_id;
        $subscriptionDetail->driver = 0;
        $subscriptionDetail->start_date = Carbon::now();
        $subscriptionDetail->end_date = Carbon::now()->addDays(env('FREE_TRIAL_DURATION_DAYS', 7));
        $subscriptionDetail->type = 'TRIAL';
        $subscriptionDetail->currency = 'USD';
        $subscriptionDetail->price = 0;
        $subscriptionDetail->save();
    }




    public function forgetPassword(Request $request)
    {
        $request->validate([
            'mobile_number' => 'required',
        ]);

        // Fetch the user by mobile_number
        $user = User::where('mobile_number', $request->mobile_number)->first();
        $otp = mt_rand(1000, 9999);

        if ($user) {
            if (!$user->is_verified) {
                return response()->json(['status_code' => 2, 'data' => [], 'message' => 'User not verified.']);
            }
            $this->sendOtpSMS($request->mobile_number, $otp);
            $user->update([
                'otp' => $otp,

            ]);
            // Send OTP to the user's email
            $data = [
                'name' => $user->name,
                'otp' => $otp
            ];
            $body = view('email.otp_verification', $data)->render();
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $subject = 'Verify your email';
            Helper::sendEmail($user->email, $subject, $body, $headers);

            return response()->json(['status_code' => 1, 'data' => ['id' => $user->id], 'message' => 'OTP has been sent to your registered email address. You can later change your password.', 'otp' => $otp]);
        } else {
            return response()->json(['status_code' => 2, 'data' => [], 'message' => 'User not registered.']);
        }
    }

    public function forgetPasswordVerifyUser(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'otp' => 'required'
        ]);

        $user = User::find($request->input('id'));

        if (!$user) {
            return response()->json(['status_code' => 2, 'data' => [], 'message' => 'User not found']);
        }
        if ($user->otp == $request->input('otp')) {
            $uid = Str::uuid()->toString();
            $user->update([
                'otp' => '',
                'verification_uid' => $uid
            ]);

            return response()->json(['status_code' => 1, 'data' => ['id' => $user->id, 'uid' => $uid], 'message' => 'Email verified. Continue to change your password']);
        }
        return response()->json(['status_code' => 2, 'data' => [], 'message' => 'Invalid Otp']);
    }


    public function forgetPasswordChangePassword(Request $request)
    {
        $request->validate([
            'id' => 'required|integer',
            'password' => 'required',
            'verification_uid' => 'required|string'
        ]);

        $user = User::where('id', $request->input('id'))
            ->where('verification_uid', $request->input('verification_uid'))
            ->first();

        if (!$user) {
            return response()->json(['status_code' => 2, 'data' => [], 'message' => 'User not found']);
        }

        $user->update([
            'password' =>  bcrypt($request->input('password')),
            'verification_uid' => ''
        ]);

        return response()->json(['status_code' => 1, 'data' => [], 'message' => 'Password changed.']);
    }
    public function meProfile()
    {
        return response()->json(['status_code' => 1, 'data' => [auth()->user()], 'message' => 'User profile fetched successfully']);
    }




    public function createContact(Request $request)
    {

        // Validate incoming request data
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'contact' => 'required|string',
            'type' => 'required|string',
            'reference_id' => 'nullable|string',
            'notes' => 'nullable|array',
        ]);

        // Step 1: Create a Contact
        $contactResponse = Http::withBasicAuth(
            Helper::getRazorpayKeyId(),
            Helper::getRazorpayKeySecret()
        )
            ->post('https://api.razorpay.com/v1/contacts', [
                'name' => $request->input('name'),
                'email' => $request->input('email'),
                'contact' => $request->input('contact'),
                'type' => $request->input('type'),
                'reference_id' => $request->input('reference_id'),
                'notes' => $request->input('notes', []),
            ]);

        // Check if the contact creation was successful
        if ($contactResponse->failed()) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Failed to create contact.',
                'error' => $contactResponse->json(),
            ]);
        }

        // If successful, return the contact details
        return response()->json([
            'status_code' => 1,
            'message' => 'Contact created successfully.',
            'contact' => $contactResponse->json(),
        ]);
    }
    public function createFundAccount(Request $request)
    {
        $request->validate([
            'contact_id' => 'required|string',
            'name' => 'required|string',
            'ifsc' => 'required|string',
            'account_number' => 'required|string',
        ]);

        try {
            // Make API request using Http facade with Basic Auth
            $response = Http::withBasicAuth(
                Helper::getRazorpayKeyId(),
                Helper::getRazorpayKeySecret()
            )->post('https://api.razorpay.com/v1/fund_accounts', [
                'contact_id' => $request->input('contact_id'),
                'account_type' => 'bank_account',
                'bank_account' => [
                    'name' => $request->input('name'),
                    'ifsc' => $request->input('ifsc'),
                    'account_number' => $request->input('account_number'),
                ],
            ]);

            // Handle response
            if ($response->successful()) {
                return response()->json([
                    'status_code' => 1,
                    'message' => 'Fund account created successfully',
                    'data' => $response->json(),
                ]);
            } else {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'Error creating fund account',
                    'error' => $response->json(),
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Error occurred while processing the request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function validateFundAccount(Request $request)
    {
        $request->validate([
            'account_number' => 'required|string',
            'fund_account_id' => 'required|string',
            'amount' => 'required|integer',
            'currency' => 'required|string|in:INR',
            'notes' => 'nullable|array'
        ]);

        try {
            // Make API request using Http facade with Basic Auth
            $response = Http::withBasicAuth(
                Helper::getRazorpayKeyId(),
                Helper::getRazorpayKeySecret()
            )->post('https://api.razorpay.com/v1/fund_accounts/validations', [
                'account_number' => $request->input('account_number'),
                'fund_account' => [
                    'id' => $request->input('fund_account_id'),
                ],
                'amount' => $request->input('amount'),
                'currency' => $request->input('currency'),
                'notes' => $request->input('notes', [])
            ]);

            // Handle response
            if ($response->successful()) {
                return response()->json([
                    'status_code' => 1,
                    'message' => 'Fund account validated successfully',
                    'data' => $response->json(),
                ]);
            } else {
                return response()->json([
                    'status_code' => 0,
                    'message' => 'Error validating fund account',
                    'error' => $response->json(),
                ], $response->status());
            }
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 0,
                'message' => 'Error occurred while processing the request',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
