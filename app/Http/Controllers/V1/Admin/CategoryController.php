<?php

namespace App\Http\Controllers\V1\Admin;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategorySize;
use App\Models\Size;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;


class CategoryController extends Controller
{
    public function fetchCategories(Request $request)
    {
        // Check if the gender query parameter is provided
        if ($request->has('gender') && $request->input('gender') !== 'ALL') {
            // Retrieve categories based on the gender
            $categories = Category::where('gender', $request->input('gender'))->get();
        } else {
            // Retrieve all categories if no gender is specified or if gender is 'ALL'
            $categories = Category::all();
        }

        // Return the categories as a JSON response
        return response()->json([
            'status_code' => 1,
            'data' => $categories,
            'message' => 'Categories fetched successfully.'
        ]);
    }
    public function fetchCategoryById(Request $request)
    {
        // Validate the request to ensure category_id is provided
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
        ]);
        $categoryId = $request->input('category_id');

        // Fetch the category details along with sizes using a join query
        $categories = Category::select('categories.id', 'categories.category_name', 'categories.media_url', 'categories.gender', 'sizes.id as size_id', 'sizes.size_name')
            ->leftJoin('categories_sizes', 'categories.id', '=', 'categories_sizes.category_id')
            ->leftJoin('sizes', 'categories_sizes.size_id', '=', 'sizes.id')
            ->where('categories.id', $categoryId)
            ->get()
            ->groupBy('id');

        // Structure the response data
        $response = $categories->map(function ($category) {
            return [
                'id' => $category->first()->id,
                'category_name' => $category->first()->category_name,
                'media_url' => $category->first()->media_url,
                'gender' => $category->first()->gender,
                'sizes' => $category->map(function ($item) {
                    return [
                        'id' => $item->size_id,
                        'size_name' => $item->size_name,
                    ];
                })->filter()->values(),
            ];
        })->first();

        // Return the response as JSON
        return response()->json([
            'status_code' => 1,
            'data' => $response,
            'message' => 'Category fetched successfully.'
        ]);
    }

    public function fetchAllCategories()
    {
        try {
            $categories = Category::select('categories.id', 'categories.category_name',  'categories.media_url', 'categories.gender', 'sizes.id as size_id', 'sizes.size_name')
                ->leftJoin('categories_sizes', 'categories.id', '=', 'categories_sizes.category_id')
                ->leftJoin('sizes', 'categories_sizes.size_id', '=', 'sizes.id')
                ->get()
                ->groupBy('id');


            $formattedCategories = $categories->map(function ($categoryGroup) {
                $category = $categoryGroup->first();

                return [
                    'id' => $category->id,
                    'category_name' => $category->category_name,
                    'gender' => $category->gender,
                    'media_url' => $category->media_url,
                    'sizes' => $categoryGroup->map(function ($item) {
                        return [
                            'id' => $item->size_id,
                            'size_name' => $item->size_name,
                        ];
                    })->filter()->values(),
                ];
            })->values();

            // Return the categories as a JSON response
            return response()->json([
                'status_code' => 1,
                'data' => $formattedCategories,
                'message' => 'Categories fetched successfully.'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status_code' => 2, 'message' => $e->getMessage()], 500);
        }
    }
    public function fetchSizesByCategoryId(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
        ]);

        try {
            $category_id = $request->input('category_id');

            if ($category_id) {
                // Fetch sizes for the given category_id using join
                $sizes = DB::table('categories_sizes')
                    ->join('sizes', 'categories_sizes.size_id', '=', 'sizes.id')
                    ->where('categories_sizes.category_id', $category_id)
                    ->select('sizes.id', 'sizes.size_name')
                    ->get();
            } else {
                // Fetch all sizes
                $sizes = Size::all();
            }

            return response()->json([
                'status_code' => 1,
                'data' => $sizes,
                'message' => 'Sizes fetched successfully.'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status_code' => 2,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function addCategory(Request $request)
    {
        // Validate the incoming request

        $request->validate([
            'category_name' => 'required|string',
            'gender' => 'required|string',
            'size_id' => 'required|array',
            'category_image' => 'required|image|mimes:jpeg,png,jpg,gif,svg',
        ]);

        try {
            $existingCategory = Category::where('category_name', strtoupper($request->input('category_name')))->first();
            if ($existingCategory) {
                return response()->json(['status_code' => 2, 'data' => [], 'message' => 'Category name already exists.']);
            }

            $mediaUrl = null;
            if ($request->hasFile('category_image')) {
                $file = $request->file('category_image');
                $dir = '/uploads/categories/';
                $mediaUrl = Helper::saveImageToServer($file, $dir); // Save image and get the path
            }


            // Create the new category
            $category = Category::create([
                'category_name' => strtoupper($request->input('category_name')),
                'gender' => $request->input('gender'),
                'media_url' => $mediaUrl, // Save the media URL
            ]);

            // Create the size variants for the new category
            $sizeVariants = [];
            foreach ($request->input('size_id') as $sizeId) {
                $categorySizeVariant = CategorySize::create([
                    'category_id' => $category->id,
                    'size_id' => $sizeId,
                ]);
                $sizeVariants[] = $categorySizeVariant;
            }


            // Return the newly created category and its size variants as a JSON response
            return response()->json([
                'status_code' => 1,
                'data' => [
                    'category' => $category,
                    'category_sizes' => $sizeVariants,

                ],
                'message' => 'Category created successfully.'
            ], 201);
        } catch (\Exception $e) {
            // Return an error response
            return response()->json([
                'status_code' => 2,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function addSize(Request $request)
    {
        $request->validate([
            'size_name' => 'required|string|max:255',
        ]);

        $existingSize = Size::where('size_name', strtoupper($request->input('size_name')))->first();
        if ($existingSize) {

            return response()->json(['status_code' => 2, 'data' => [], 'message' => 'Size name already exists.']);
        }

        $size = new Size;
        $size->size_name = strtoupper($request->input('size_name'));
        $size->save();

        return response()->json([
            'status_code' => 1,
            'data' => [
                'size' => $size,
            ],
            'message' => 'Size created successfully.'
        ]);
    }
    public function updateSize(Request $request)
    {
        $request->validate([
            'size_name' => 'required|string|max:255',
            'size_id' => 'required|integer|exists:sizes,id',
        ]);

        $existingSize = Size::where('size_name', strtoupper($request->input('size_name')))
            ->where('id', '!=', $request->input('size_id'))
            ->first();
        if ($existingSize) {
            return response()->json(['status_code' => 2, 'data' => [],   'message' => 'Size name already exists.']);
        }
        $size = Size::findOrFail($request->size_id);
        $size->size_name = strtoupper($request->input('size_name'));
        $size->save();

        return response()->json([
            'status_code' => 1,
            'data' => [
                'size' => $size,
            ],
            'message' => 'Size updated successfully.'
        ]);
        return response()->json(['status_code' => 2, 'data' => [],]);
    }
    public function fetchAllSizes()
    {
        try {
            $sizes = Size::all();
            return response()->json(['status_code' => 1, 'data' => $sizes, 'message' => 'Sizes fetched successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status_code' => 2, 'message' => $e->getMessage()], 500);
        }
    }
    public function fetchSizeById(Request $request)
    {
        try {
            // Validate the request data
            $request->validate([
                'size_id' => 'required|integer|exists:sizes,id', // Validate that size_id is required and exists in sizes table
            ]);

            // Find the size by size_id
            $size = Size::findOrFail($request->size_id);

            // Return the size data
            return response()->json(['status_code' => 1, 'data' => $size, 'message' => 'Size fetched successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json(['status_code' => 2, 'message' => $e->getMessage()], 500);
        }
    }


    public function deleteSize(Request $request)
    {
        // Validate the request data
        $request->validate([
            'size_id' => 'required|integer|exists:sizes,id', // Validate that size_id is required and exists in sizes table
        ]);

        // Find the size by size_id
        $size = Size::findOrFail($request->size_id);

        // Delete the size
        $size->delete();

        $sizes = Size::all();

        // Return the response with the updated sizes
        return response()->json([
            'status_code' => 1,
            'data' => $sizes,
            'message' => 'Sizes fetched successfully.'
        ], 200);
    }

    public function updateCategory(Request $request)
    {
        // Validate the incoming request

        $request->validate([
            'category_id' => 'required|exists:categories,id',
            'category_name' => 'required|string',
            'gender' => 'required|string',
            'size_id' => 'required|array',

        ]);
        $existingCategory = Category::where('category_name', strtoupper($request->input('category_name')))
            ->where('id', '!=', $request->input('category_id'))
            ->first();
        if ($existingCategory) {
            return response()->json(['status_code' => 2, 'data' => [],  'message' => 'Category name already exists.']);
        }
        // Start a database transaction
        DB::beginTransaction();

        try {
            // Find the category by ID
            $category = Category::find($request->input('category_id'));

            // Check if category exists
            if (!$category) {
                return response()->json([
                    'status_code' => 2,
                    'message' => 'Category not found.'
                ], 200);
            }
            if ($request->hasFile('category_image')) {
                $file = $request->file('category_image');
                $dir = '/uploads/categories/';
                $path = Helper::saveImageToServer($file, $dir);
                $category->media_url = $path;
            }
            // Delete existing entries in the CategorySize table for the given category_id
            CategorySize::where('category_id', $category->id)->delete();

            // Insert new entries in the CategorySize table
            $sizeVariants = [];
            foreach ($request->input('size_id') as $sizeId) {
                $categorySizeVariant = CategorySize::create([
                    'category_id' => $category->id,
                    'size_id' => $sizeId,
                ]);
                $sizeVariants[] = $categorySizeVariant;
            }

            // Update the category
            $category->category_name = strtoupper($request->input('category_name'));
            $category->gender = $request->input('gender');
            $category->save();

            // Commit the transaction
            DB::commit();

            // Return the updated category and its size variants as a JSON response
            return response()->json([
                'status_code' => 1,
                'data' => [
                    'category' => $category,
                    'category_sizes' => $sizeVariants
                ],
                'message' => 'Category updated successfully.'
            ], 200);
        } catch (\Exception $e) {
            // Rollback the transaction in case of an error
            DB::rollBack();

            // Return an error response
            return response()->json([
                'status_code' => 2,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // public function updateCategory(Request $request)
    // {
    //     // Validate the incoming request
    //     $request->validate([
    //         'category_name' => 'required|string',
    //     ]);

    //     // Find the category by ID
    //     $category = Category::find($request->input('category_id'));
    //     $sizeVariants = [];
    //     foreach ($request->input('size_id') as $sizeId) {
    //         $categorySizeVariant = CategorySize::create([
    //             'category_id' => $category->id,
    //             'size_id' => $sizeId,
    //         ]);
    //         $sizeVariants[] = $categorySizeVariant;
    //     }
    //     // Check if category exists
    //     if (!$category) {
    //         return response()->json([
    //             'status_code' => 2,
    //             'message' => 'Category not found.'
    //         ], 200);
    //     }

    //     // Update the category
    //     $category->category_name = $request->input('category_name');
    //     $category->gender = $request->input('gender');
    //     $category->save();

    //     // Return the updated category as a JSON response
    //     return response()->json([
    //         'status_code' => 1,
    //         'data' => $category,
    //         'message' => 'Category updated successfully.'
    //     ], 200);
    // }
    public function deleteCategory($id)
    {
        // Find the category by ID
        $category = Category::find($id);

        // Check if category exists
        if (!$category) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Category not found.'
            ], 200);
        }
        $category->delete();

        // Return a success message as a JSON response
        return response()->json([
            'status_code' => 1,
            'message' => 'Category deleted successfully.'
        ], 200);
    }
}
