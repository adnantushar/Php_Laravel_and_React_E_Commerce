<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductGallery;
use App\Traits\Helpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use JWTAuth;
use Tymon\JWTAuth\Exceptions\JWTException;

class ProductController extends Controller
{
    use Helpers;
    protected $user;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        // Fetch Product With User and Image
        $query = Product::with('user','gallery');
        $query->orderBy('id', 'DESC');
        $products = $query->paginate(10);
        return response()->json(['products' => $products], 200);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            //Validate data
            if(($errors = $this->validateData($request)) && count($errors) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please fix these errors',
                    'errors' => $errors], 500);
            }

            //Request is valid, create new product
            $product = auth()->user()->products()->create([
                'name' => $request->name,
                'sku' => $request->sku,
                'price' => $request->price,
                'quantity' => $request->quantity,
                'description' => $request->description
            ]);

            // Upload Images
            $this->uploadImages($request, $product);

            //Product created, return success response
            return response()->json([
                'success' => true,
                'message' => 'Product created successfully',
                'data' => $product
            ],201);
        } catch (\Exception $e) {
            // Delete Saving Product
            $product->delete();
            return response()->json([
                'success' => False,
                'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {

        $product = Product::with('gallery')->find($id);
        // If Product Not Found
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry, product not found.'
            ], 400);
        }
        return $product;
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function edit(Product $product)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
             $product = Product::findOrFail($id);
             // Validate Data
             if(($errors = $this->validateData($request, $id)) && count($errors) > 0) {
                 return response()->json([
                      'success' => 0,
                      'message' => 'Please fix these errors',
                      'errors' => $errors], 500);
             }

            //Request is valid, update product
            $product->name = $request->name;
            $product->sku = $request->sku;
            $product->price = $request->price;
            $product->quantity = $request->quantity;
            $product->description = $request->description;
            $product->save();
            // dd($product);
            // upload images
            $this->uploadImages($request, $product);

            //Product updated, return success response
            return response()->json([
                'success' => true,
                'message' => 'Product updated successfully',
                'data' => $product
            ], 200);
        }catch (\Exception $e) {
            return response()->json([
                'success' => 0,
                'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $product = Product::with('gallery')->findOrFail($id);

            // Delete Product Image
            foreach ($product->gallery as $gallery) {
                if(!empty($gallery->image)) {
                    foreach ($gallery->image_url as $dir => $url) {
                        $this->deleteFile(
                          base_path('public').'/uploads/'
                          . $gallery->product_id . '/'
                          . $dir . '/' . $gallery->image);
                    }
                    $this->deleteFile(
                        base_path('public').'/uploads/'
                        . $gallery->product_id . '/'
                        . $gallery->image);
                }
            }

            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Product deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => 0,
                'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Search for Product
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function search(Request $request)
    {
        $query = Product::with('user', 'gallery');
        $this->filterProduct($request, $query);
        $query->orderBy('id', 'DESC');
        $products = $query->paginate(10);
        return response()->json(['products' => $products], 200);
    }

    /**
     * validate
     *
     * @param $request
     * @param null $id
     * @throws \Exception
     */
    protected function validateData($request, $id = null)
    {
        $payload = [
            'name' => 'required|string',
            'sku' => 'required',
            'price' => 'required|numeric',
            'quantity' => 'required|numeric',
            'description' => 'required',
        ];
        // Only jpeg,png,jpg,gif,svg Image With Max Size 10000
        if(!$id) {
            $payload += ['image' => 'required',
                'image.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:10000'
            ];
        }
        $validator = Validator::make($request->all(), $payload);
        if($validator->fails()) {
            return $validator->errors();
        }
        return [];
    }

    /**
     * upload images
     *
     * @param $request
     * @param $product
     */
    protected function uploadImages($request, $product)
    {
        $this->createProductUploadDirs($product->id, $this->imagesSizes);
        $uploaded_files = $this->uploadFiles($request, 'image',
            base_path('public').'/uploads/' . $product->id);
        foreach ($uploaded_files as $uploaded_file) {
            $productGallery = new ProductGallery();
            $productGallery->image = $uploaded_file;
            $product->gallery()->save($productGallery);
            // start resize images
            foreach ($this->imagesSizes as $dirName => $imagesSize) {
                $this->resizeImage(base_path('public').'/uploads/'
                    . $product->id . '/' . $uploaded_file,
                    base_path('public').'/uploads/' . $product->id
                    . '/' . $dirName . '/' . $uploaded_file,
                    $imagesSize['width'],
                    $imagesSize['height']);
            }
        }
    }


    /**
     * filter product
     *
     * @param $request
     * @param $query
     */
    private function filterProduct($request, $query)
    {
        if($request->has('id')) {
            $query->where('id', $request->id);
        }
        if($request->has('name')) {
            $query->where('name', 'like', "%$request->name%");
        }
        if($request->has('sku')) {
            $query->where('sku', $request->sku);
        }
        if($request->has('from_price')) {
            $query->where('price', '>=', $request->from_price);
        }
        if($request->has('to_price')) {
            $query->where('price', '<=', $request->to_price);
        }
        if($request->has('quantity')) {
            $query->where('quantity', $request->quantity);
        }
    }
}
