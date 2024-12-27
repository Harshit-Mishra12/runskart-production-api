<?php

namespace App\Http\Controllers\V1\Admin;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\BrandRetailerVariant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RetailerController extends Controller
{
    public function addRetailer(Request $request)
    {
        $request->validate([
            'retailer_name' => 'required',
            'showroom_name' => 'required',
            'shop_address' => 'required|string',
            'email' => 'required|email',
            'mobile_number' => 'required',
            'role' => 'required|in:RETAILER,BRAND,BRANDRETAILER',
            'brand_id' => 'array|nullable',
        ]);
        if ($request->input('role') === 'BRANDRETAILER') {
            $request->validate(['brand_id' => 'required|array']);
        }
        $password = Str::random(10);

        $user = User::where('email', $request->email)->first();

        if ($user) {
            return response()->json(['status_code' => 2, 'data' => [], 'message' => 'User already registered.']);
        } else {
            $user = new User();
            $user->retailer_name = strtoupper($request->input('retailer_name'));
            $user->showroom_name = $request->input('showroom_name');
            $user->shop_address = $request->input('shop_address');
            $user->email = $request->input('email');
            $user->mobile_number = $request->input('mobile_number');
            $user->password = bcrypt($password);
            $user->role = $request->input('role');
            $user->status = 'ACTIVE';
            $user->is_verified = true;
            $user->save();

            if ($request->input('role') === 'BRANDRETAILER' && !empty($request->input('brand_id'))) {
                // Iterating over brand_id array and saving to brand_retailer_variants table using Eloquent
                foreach ($request->input('brand_id') as $brandId) {
                    BrandRetailerVariant::create([
                        'retailer_id' => $user->id,
                        'brand_id' => $brandId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $data = [
            'name' => $user->retailer_name,
            'email' => $user->email,
            'password' => $password,
            'login_link' => 'https://musical-crostata-bce349.netlify.app/login'

        ];
        $body = view('email.verification_email', $data)->render();
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $subject = 'Credentials From Attire';
        Helper::sendPhpEmail($user->email, $subject, $body, $headers);

        return response()->json(['status_code' => 1, 'data' => ['user' => $user], 'message' => 'User registered successfully.']);
    }
    public function updateRetailer(Request $request)
    {
        $request->validate([
            'retailer_name' => 'required',
            'showroom_name' => 'required',
            'shop_address' => 'required|string',
            'email' => 'required|email',
            'mobile_number' => 'required',
            'role' => 'required|in:RETAILER,BRAND,BRANDRETAILER',
            'brand_id' => 'array|nullable',
        ]);
        if ($request->input('role') === 'BRANDRETAILER') {
            $request->validate(['brand_id' => 'required|array']);
        }


        $user = User::find($request->input('id'));

        if (!$user) {
            return response()->json(['status_code' => 2, 'message' => 'User not found.']);
        }

        if (User::where('email', $request->email)->where('id', '!=', $request->input('id'))->exists()) {
            return response()->json(['status_code' => 2, 'message' => 'Email already in use by another user.']);
        }

        $user->retailer_name = strtoupper($request->input('retailer_name'));
        $user->showroom_name = $request->input('showroom_name');
        $user->shop_address = $request->input('shop_address');
        $user->email = $request->input('email');
        $user->mobile_number = $request->input('mobile_number');
        if ($request->filled('password')) {
            $user->password = bcrypt($request->input('password'));
        }
        $user->role = $request->input('role');
        $user->save();
        BrandRetailerVariant::where('retailer_id', $request->input('id'))->delete();
        if ($request->input('role') === 'BRANDRETAILER' && !empty($request->input('brand_id'))) {
            // Iterating over brand_id array and saving to brand_retailer_variants table using Eloquent
            foreach ($request->input('brand_id') as $brandId) {
                BrandRetailerVariant::create([
                    'retailer_id' => $request->input('id'),
                    'brand_id' => $brandId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        return response()->json(['status_code' => 1, 'data' => ['user' => $user], 'message' => 'User updated successfully.']);
    }

    public function fetchAllRetailers(Request $request)
    {
        try {
            $userRole = $request->input('user_role');
            // Base query to fetch users and include the status value
            $query = DB::table('users')
                ->select('users.id', 'users.retailer_name', 'users.role', 'users.showroom_name', 'users.email', 'users.shop_address', 'users.status', DB::raw('COUNT(products.id) as product_count'))
                ->leftJoin('products', function ($join) {
                    $join->on('users.id', '=', 'products.retailer_id')
                        ->whereIn('products.status', ['Active', 'ACTIVE']);
                })
                ->whereIn('users.role', ['RETAILER', 'BRAND', 'BRANDRETAILER']) // Fetch both retailers and brands
                ->groupBy('users.id', 'users.retailer_name', 'users.role', 'users.showroom_name', 'users.email', 'users.shop_address', 'users.status');

            // Modify the query based on the user role
            if ($userRole === 'retailer') {
                $query->where('users.role', 'RETAILER');
            } elseif ($userRole === 'brand') {
                $query->where('users.role', 'BRAND');
            } elseif ($userRole === 'brandRetailer') {
                $query->where('users.role', 'BRAND');
            } elseif ($userRole === 'all') {
                $query->whereIn('users.role', ['RETAILER', 'BRAND', 'BRANDRETAILER']);
            } else {
                return response()->json(['status_code' => 2, 'message' => 'Invalid user role provided']);
            }

            $retailers = $query->get();

            return response()->json(['status_code' => 1, 'message' => 'success', 'data' => $retailers]);
        } catch (\Exception $e) {
            return response()->json(['status_code' => 2, 'message' => $e->getMessage()]);
        }
    }
    public function fetchAllActiveRetailers(Request $request)
    {
        try {
            $userRole = $request->input('user_role');
            // Base query to fetch users and include the status value
            $query = DB::table('users')
                ->select('users.id', 'users.retailer_name', 'users.role', 'users.showroom_name', 'users.email', 'users.shop_address', 'users.status', DB::raw('COUNT(products.id) as product_count'))
                ->leftJoin('products', function ($join) {
                    $join->on('users.id', '=', 'products.retailer_id')
                        ->whereIn('products.status', ['Active', 'ACTIVE']);
                })
                ->whereIn('users.role', ['RETAILER', 'BRAND','BRANDRETAILER']) // Fetch both retailers and brands
                ->whereIn('users.status', ['ACTIVE'])
                ->groupBy('users.id', 'users.retailer_name', 'users.role', 'users.showroom_name', 'users.email', 'users.shop_address', 'users.status');

            // Modify the query based on the user role
            if ($userRole === 'retailer') {
                $query->where('users.role', 'RETAILER');
            } elseif ($userRole === 'brand') {
                $query->where('users.role', 'BRAND');
            } else {
                $query->whereIn('users.role', ['RETAILER', 'BRAND','BRANDRETAILER']);
            }

            $retailers = $query->get();

            return response()->json(['status_code' => 1, 'message' => 'success', 'data' => $retailers]);
        } catch (\Exception $e) {
            return response()->json(['status_code' => 2, 'message' => $e->getMessage()]);
        }
    }
    public function fetchRetailerById(Request $request)
    {
        try {
            // Extract the ID from the request
            $retailerId = $request->input('id');

            // Fetch the retailer from the users table based on the ID
            $user = DB::table('users')
                ->where('id', $retailerId)
                ->whereIn('role', ['RETAILER', 'BRAND', 'BRANDRETAILER'])
                ->first();

            // Check if retailer exists
            if ($user) {
                // Return retailer data
                return response()->json(['status_code' => 1, 'message' => 'success', 'data' => $user]);
            } else {
                // Retailer not found
                return response()->json(['status_code' => 2, 'message' => 'user not found'], 404);
            }
        } catch (\Exception $e) {
            // Error occurred
            return response()->json(['status_code' => 3, 'message' => $e->getMessage()], 500);
        }
    }



    public function changeRetailerStatus(Request $request)
    {
        try {
            // Validate the request parameters
            $validator = Validator::make(['id' => $request->input('id')], [
                'id' => 'required|exists:users,id'
            ]);

            // Check if validation fails
            if ($validator->fails()) {
                return response()->json(['status_code' => 2, 'message' => $validator->errors()->first()], 400);
            }

            // Find the retailer
            $retailer = User::findOrFail($request->input('id'));

            // Toggle status between ACTIVE and INACTIVE
            $newStatus = $retailer->status === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE';
            $retailer->update(['status' => $newStatus]);

            $userRole = $request->input('user_role');

            // Base query to fetch users and include the status value
            // $query = DB::table('users');
            $query = DB::table('users')
                ->select('users.id', 'users.retailer_name', 'users.role', 'users.showroom_name', 'users.email', 'users.shop_address', 'users.status', DB::raw('COUNT(products.id) as product_count'))
                ->leftJoin('products', function ($join) {
                    $join->on('users.id', '=', 'products.retailer_id')
                        ->whereIn('products.status', ['Active', 'ACTIVE']);
                })
                ->whereIn('users.role', ['RETAILER', 'BRAND', 'BRANDRETAILER']) // Fetch both retailers and brands
                ->groupBy('users.id', 'users.retailer_name', 'users.role', 'users.showroom_name', 'users.email', 'users.shop_address', 'users.status');

            // Modify the query based on the user role
            if ($userRole === 'retailer') {
                $query->where('users.role', 'RETAILER');
            } elseif ($userRole === 'brand') {
                $query->where('users.role', 'BRAND');
            } elseif ($userRole === 'brandRetailer') {
                $query->where('users.role', 'BRANDRETAILER');
            } elseif ($userRole === 'all') {
                $query->whereIn('users.role', ['RETAILER', 'BRAND', 'BRANDRETAILER']);
            } else {
                return response()->json(['status_code' => 2, 'message' => 'Invalid user role provided']);
            }
            $retailers = $query->get();

            return response()->json(['status_code' => 1, 'message' => 'Retailer status updated', 'data' => $retailers]);
        } catch (\Exception $e) {
            return response()->json(['status_code' => 2, 'message' => $e->getMessage()]);
        }
    }

    public function fetchAllBrands()
    {
        try {
            // Fetch all brands with role 'BRAND'
            $brands = DB::table('users')
                ->where('role', 'BRAND')
                ->whereIn('status', ['Active', 'ACTIVE'])
                ->get();

            return response()->json(['status_code' => 1, 'message' => 'success', 'data' => $brands]);
        } catch (\Exception $e) {
            // Error occurred
            return response()->json(['status_code' => 2, 'message' => $e->getMessage()], 200);
        }
    }
    public function deleteAssociatedBrandRetailerFromBrand(Request $request)
    {
        // Validate the input
        $validatedData = $request->validate([
            'brand_id' => 'required|exists:users,id',
            'retailer_id' => 'required|exists:users,id',
        ]);

        // Extract the brand_id and retailer_id from the request
        $brandId = $validatedData['brand_id'];
        $retailerId = $validatedData['retailer_id'];

        // Delete the associated BrandRetailerVariant entries
        $deletedRows = BrandRetailerVariant::where('brand_id', $brandId)
            ->where('retailer_id', $retailerId)
            ->delete();

        if ($deletedRows > 0) {
            return response()->json([
                'status_code' => 1,
                'message' => 'Associated BrandRetailerVariant entries deleted successfully',
                'deleted_rows' => $deletedRows
            ]);
        } else {
            return response()->json([
                'status_code' => 2,
                'message' => 'No matching entries found to delete'
            ]);
        }
    }
}
