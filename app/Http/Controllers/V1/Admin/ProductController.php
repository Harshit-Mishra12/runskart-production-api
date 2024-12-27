<?php

namespace App\Http\Controllers\V1\Admin;

use App\Helpers\Helper;
use App\Http\Controllers\Controller;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\SizeVariant;
use App\Models\ColorVariant;
use App\Models\BrandRetailerVariant;
use App\Models\User;
use Carbon\Carbon;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function addProduct(Request $request)
    {
        // Validate request data
        $request->validate([
            'name' => 'required|string',
            'category_id' => 'required',
            'description' => 'required|string',
            'price' => 'required',
            'sku' => 'required|string',
            'color_code' => 'required|string',
            'retailer_id' => 'required|exists:users,id', // Assuming users table holds retailer data
            'mode_type' => 'required|string|in:ONLINE,OFFLINE,ALL',
            // 'status' => 'required|string|in:Active,Inactive',
            'product_images.*' => 'required|image|mimes:jpeg,png,jpg,gif,svg',
            'size_variants' => 'required|array',
            'color_variants.*.secondary_product_id' => 'required|exists:products,id',
        ]);
        $category = Category::find($request->category_id);

        // Check if category exists
        if (!$category) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Category with input category Id not found.'
            ], 200);
        }
        // Find the category by ID
        $retailer = User::find($request->retailer_id);

        // Check if category exists
        if (!$retailer) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Reatiler with input Retailer Id not found.'
            ], 200);
        }



        // Create product
        $product = new Product();
        $product->name = strtoupper($request->input('name'));
        $product->category_id = $request->category_id;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->sku = $request->sku;
        $product->color_code = $request->color_code;
        $product->retailer_id = $request->retailer_id;
        $product->mode_type = $request->mode_type;
        $product->status = 'Active';
        $product->save();

        // Create product images
        if ($request->hasfile('product_images')) {
            $dir = '/uploads/products/';
            $images = $request->file('product_images');
            foreach ($images as $file) {

                $path = Helper::saveImageToServer($file, $dir);

                $productImage = new ProductImage();
                $productImage->product_id = $product->id;
                $productImage->media_url = $path;
                $productImage->save();
            }
        }

        // Create size variants
        if ($request->has('size_variants')) {
            foreach ($request->size_variants as $size) {
                $sizeVariant = new SizeVariant();
                $sizeVariant->product_id = $product->id;
                $sizeVariant->size_name = $size;
                $sizeVariant->save();
            }
        }

        // Create color variants
        $matchingProducts = Product::where('sku', $request->input('sku'))->get();
        if (count($matchingProducts) > 0) {
            foreach ($matchingProducts as $matchingProduct) {
                if ($matchingProduct->id !== $product->id) {
                    $colorVariant = new ColorVariant();
                    $colorVariant->primary_product_id = $matchingProduct->id;
                    $colorVariant->secondary_product_id = $product->id;
                    $colorVariant->color_code = $request->color_code;
                    $colorVariant->save();
                }
            }
        }

        // Create color variants for each matching product
        foreach ($matchingProducts as $matchingProduct) {
            if ($matchingProduct->id !== $product->id) {
                $colorVariant = new ColorVariant();
                $colorVariant->primary_product_id = $product->id;
                $colorVariant->secondary_product_id = $matchingProduct->id;
                $colorVariant->save();
            }
        }


        return response()->json(['status_code' => 1, 'message' => 'Product created successfully']);
    }
    public function updateProduct(Request $request)
    {
        // Validate request data
        $request->validate([
            'name' => 'required|string',
            'category_id' => 'required',
            'description' => 'required|string',
            'price' => 'required',
            'color_code' => 'required|string',
            'retailer_id' => 'required|exists:users,id', // Assuming users table holds retailer data
            'mode_type' => 'required|string|in:ONLINE,OFFLINE,ALL',
            'status' => 'required|string|in:Active,InActive',
            'deleted_product_image_id' => 'nullable|array',
            'product_images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg',
            'size_variants' => 'required|array',
            'color_variants.*.secondary_product_id' => 'nullable|exists:products,id',
        ]);

        // Find the existing product
        $product = Product::find($request->product_id);

        // Check if the product exists
        if (!$product) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Product not found.'
            ]);
        }

        // Find the category
        $category = Category::find($request->category_id);
        if (!$category) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Category with input category Id not found.'
            ]);
        }

        // Find the retailer
        $retailer = User::find($request->retailer_id);
        if (!$retailer) {
            return response()->json([
                'status_code' => 2,
                'message' => 'Retailer with input Retailer Id not found.'
            ]);
        }

        if ($request->status === "InActive") {
            $product->status = "InActive";
            $product->save();
            return response()->json(['status_code' => 1, 'message' => 'Product status updated to InActive.']);
        }


        // Delete product images using deleted_product_images_id
        if ($request->has('deleted_product_image_id')) {
            $deletedImageIds = $request->input('deleted_product_image_id');
            foreach ($deletedImageIds as $imageId) {
                $productImage = ProductImage::find($imageId);
                if ($productImage) {
                    // Delete the image file from the server
                    Helper::deleteImageFromServer($productImage->media_url);
                    $productImage->delete();
                }
            }
        }

        // Add new product images
        if ($request->hasfile('product_images')) {
            $dir = '/uploads/products/';
            $images = $request->file('product_images');
            foreach ($images as $file) {
                $path = Helper::saveImageToServer($file, $dir);
                $productImage = new ProductImage();
                $productImage->product_id = $product->id;
                $productImage->media_url = $path;
                $productImage->save();
            }
        }

        // Update product details
        $product->name = strtoupper($request->input('name'));
        $product->category_id = $request->category_id;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->color_code = $request->color_code;
        $product->retailer_id = $request->retailer_id;
        $product->mode_type = $request->mode_type;
        $product->status = $request->status ?? 'Active';
        $product->save();



        // Update size variants
        SizeVariant::where('product_id', $product->id)->delete();
        foreach ($request->size_variants as $size) {
            $sizeVariant = new SizeVariant();
            $sizeVariant->product_id = $product->id;
            $sizeVariant->size_name = $size;
            $sizeVariant->save();
        }

        return response()->json(['status_code' => 1, 'message' => 'Product updated successfully']);
    }

    public function fetchProductsByRetailer(Request $request)
    {
        // Validate the retailerId

        $retailer = User::find($request->retailer_id);
        if (!$retailer || !in_array($retailer->role, ['RETAILER', 'BRAND', 'BRANDRETAILER'])) {
            return response()->json(['status_code' => 1, 'message' => 'Invalid retailer ID or user is not a retailer']);
        }
        // Fetch products for the retailer based on the mode type
        $query = Product::where('retailer_id', $request->retailer_id)
            ->where('status', 'Active')
            ->with(['category', 'productImages', 'sizeVariants']);

        if ($request->mode_type !== 'ALL') {
            $query->where('mode_type', $request->mode_type);
        }
        $products = $query->get();
        // Format the response
        $productData = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'sku' => $product->sku,
                'name' => $product->name,
                'mode_type' => $product->mode_type,
                'sku' => $product->sku,
                'front_image' => $product->productImages->isNotEmpty() ? $product->productImages->first()->media_url : null,
                'all_sizes' => $product->sizeVariants->pluck('size_name'),
                'price' => $product->price,
                'description' => $product->description,
                'category_name' => $product->category->category_name
            ];
        });
        $brandVariants = BrandRetailerVariant::where('retailer_id', $request->retailer_id)->get();
        $brandIds = $brandVariants->pluck('brand_id')->toArray();
        $brands = User::whereIn('id', $brandIds)->get();

        $brand_retailer = BrandRetailerVariant::where('brand_id', $request->retailer_id)->get();
        $retailerIds = $brand_retailer->pluck('retailer_id')->unique();
        $brandRetailers = [];
        foreach ($retailerIds as $retailerId) {
            $retailerInfo = User::find($retailerId);
            if ($retailerInfo) {
                $brandRetailers[] = [
                    'retailer_id' => $retailerInfo->id,
                    'retailer_name' => $retailerInfo->retailer_name,
                    'showroom_name' => $retailerInfo->showroom_name,
                    'shop_address' => $retailerInfo->shop_address,
                    'email' => $retailerInfo->email,
                    'mobile_number' => $retailerInfo->mobile_number,
                    'role' => $retailerInfo->role,
                    'status'=>$retailerInfo->status,
                ];
            }
        }

        $retailerData = [

            'retailer_name' => $retailer->retailer_name,
            'showroom_name' => $retailer->showroom_name,
            'shop_address' => $retailer->shop_address,
            'email' => $retailer->email,
            'mobile_number' => $retailer->mobile_number,
            'role' => $retailer->role,
            'brands' => $brands->map(function ($brand) {
                return [
                    'id' => $brand->id,
                    'name' => $brand->retailer_name,
                ];
            }),
        ];
        return response()->json(['status_code' => 1,  'data' => [
            'retailer' => $retailerData,
            'products' => $productData,
            'brand_retailer' => $brandRetailers
        ], 'message' => 'Products fetched successfully']);
    }
    public function fetchFilteredProducts(Request $request)
    {

        $query = Product::where('products.status', 'Active')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('users', 'products.retailer_id', '=', 'users.id')
            ->with(['category', 'productImages', 'sizeVariants'])
            ->select('products.*', 'users.retailer_name');



        // Apply filters if provided
        if ($request->has('category_id') && !empty($request->category_id)) {
            $query->whereIn('products.category_id', $request->category_id);
        }

        if ($request->has('retailer_id') && !empty($request->retailer_id)) {
            $query->whereIn('retailer_id', $request->retailer_id);
        }

        if ($request->has('mode_type') && $request->mode_type !== 'ALL') {
            $query->where('products.mode_type', $request->mode_type);
        }

        if ($request->has('gender') && $request->gender !== 'ALL') {
            $query->where('categories.gender', $request->gender);
        }
        if ($request->has('search_text') && !empty($request->search_text)) {
            $searchText = $request->input('search_text');
            $query->where('users.retailer_name', 'LIKE', "%{$searchText}%");
        }


        // Apply sorting if provided
        if ($request->has('sort_by')) {
            switch ($request->sort_by) {
                case 'newest':
                    $query->orderBy('products.created_at', 'desc');
                    break;
                case 'price-high-to-low':
                    $query->orderBy('products.price', 'desc');
                    break;
                case 'price-low-to-high':
                    $query->orderBy('products.price', 'asc');
                    break;
                default:
                    // Default sorting logic if needed
                    break;
            }
        }
        // $query->select('products.*');
        $products = $query->get();
        // return  $products;

        // Format the response
        $productData = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'retailer_name' => $product->retailer_name,
                'sku' => $product->sku,
                'mode_type' => $product->mode_type,
                'front_image' => $product->productImages->isNotEmpty() ? $product->productImages->first()->media_url : null,
                'all_sizes' => $product->sizeVariants->pluck('size_name'),
                'price' => $product->price,
                'description' => $product->description,
                'category_name' => $product->category->category_name,
                'gender' => $product->category->gender
            ];
        });

        return response()->json([
            'status_code' => 1,
            'data' => $productData,
            'message' => 'Products fetched successfully'
        ]);
    }




    public function fetchAllProducts($retailerId)
    {


        // Fetch all products for all retailers
        $products = Product::where('status', 'Active')->with(['category', 'productImages', 'sizeVariants'])->get();

        // Format the response
        $productData = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'front_image' => $product->productImages->isNotEmpty() ? $product->productImages->first()->media_url : null,
                'all_sizes' => $product->sizeVariants->pluck('size_name'),
                'price' => $product->price,
                'description' => $product->description,
                'category_name' => $product->category->category_name
            ];
        });

        return response()->json(['status_code' => 1,  'data' => [
            'products' => $productData
        ], 'message' => 'Products fetched successfully']);
    }
    public function deleteProduct(Request $request)
    {
        try {
            $product = Product::find($request->input('product_id'));

            // Check if the product exists
            if (!$product) {
                return response()->json(['status_code' => 2, 'message' => 'Product not found']);
            }

            // Update the product status to 'inactive'
            $product->status = 'InActive';
            $product->save();


            return response()->json([
                'status_code' => 1,
                'data' => $product,
                'message' => 'Product data deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json(['status_code' => 2, 'message' => $e->getMessage()], 500);
        }
    }
    public function fetchProductWithVariants($productId)
    {
        // Validate the product ID
        $product = Product::with(['category', 'productImages', 'sizeVariants'])
            ->find($productId);

        if (!$product) {
            return response()->json(['status_code' => 0, 'message' => 'Product not found'], 404);
        }

        // Fetch color variants
        // $colorVariants = ColorVariant::where('primary_product_id', $productId)
        //     ->with('secondaryProduct.productImages')
        //     ->get();
        $colorVariants = ColorVariant::where('primary_product_id', $productId)
            ->whereHas('secondaryProduct', function ($query) {
                $query->where('status', 'ACTIVE')
                    ->orWhere('status', 'Active');
            })
            ->with(['secondaryProduct' => function ($query) {
                $query->where('status', 'ACTIVE')
                    ->orWhere('status', 'Active');
            }, 'secondaryProduct.productImages'])
            ->get();
        // Format the color variants data
        $colorVariantsData = $colorVariants->map(function ($variant) {
            $secondaryProduct = $variant->secondaryProduct;
            return [
                'product_id' => $secondaryProduct->id,
                'front_image' => $secondaryProduct->productImages->isNotEmpty() ? $secondaryProduct->productImages->first()->media_url : null,
                'name' => $secondaryProduct->name,
                'description' => $secondaryProduct->description,
            ];
        });
        $productImagesData = $product->productImages->map(function ($image) {
            return [
                'id' => $image->id,
                'media_url' => $image->media_url,
            ];
        });

        // Format the product data
        $productData = [
            'name' => $product->name,
            'all_sizes' => $product->sizeVariants->pluck('size_name'),
            'price' => $product->price,
            'color_code' => $product->color_code,
            'mode_type' => $product->mode_type,
            'description' => $product->description,
            'category' => $product->category,
            'all_images' => $productImagesData,
            'color_variants' => $colorVariantsData,
            'retailer' => $product->retailer_id
        ];

        return response()->json([
            'status_code' => 1,
            'data' => $productData,
            'message' => 'Product data fetched successfully'
        ]);
    }
}
