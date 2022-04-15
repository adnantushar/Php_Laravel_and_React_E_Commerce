<?php

namespace App\Traits;

trait Helpers
{
    // Set Image Sizes
    protected $imagesSizes = [
        'main_slider' => ['width' => 484, 'height' => 441],
        'medium' => ['width' => 268, 'height' => 249],
        'medium2' => ['width' => 208, 'height' => 183],
        'small' => ['width' => 268, 'height' => 134],
        'product_gallery_slider' => ['width' => 84, 'height' => 84],
        'product_gallery_preview' => ['width' => 266, 'height' => 381],
        'cart_thumb' => ['width' => 110, 'height' => 110]
    ];

    // Create Directory
    function createProductUploadDirs($product_id , $imagesSizes)
    {
        //Directory For Each Product
        if(!file_exists(base_path('public').'/uploads/' . $product_id)) {
            @mkdir(base_path('public').'/uploads/' . $product_id, 0777);
        }
        //Directory For each Image Size
        foreach ($imagesSizes as $dirName => $imagesSize) {
            if(!file_exists(base_path('public').'/uploads/' . $product_id . '/' . $dirName)) {
                mkdir(base_path('public').'/uploads/' . $product_id . '/' . $dirName, 0777);
            }
        }
    }

    // Upload Image
    function uploadFiles($request, $filename, $destination = null)
    {
        $files_array = [];
        // Check Destination and Set
        $destination = $destination ? $destination : base_path('public').'/uploads/';

        // Upload File With New Name
        if($request->hasfile($filename)) {
            foreach($request->file($filename) as $image) {
                $ext = $image->getClientOriginalExtension();
                $file_name = time().md5(rand(100,999)).'.'.$ext;
                $image->move($destination, $file_name);
                @chmod($destination . '/' . $file_name, 777);
                $files_array[] = $file_name;
            }
        }
        return $files_array;
    }

    // Resize Image Using Intervention Image
    function resizeImage($imagePath, $savePath, $width, $height)
    {
        \Image::make($imagePath)
            ->resize($width, $height, function ($constraint) {
                $constraint->aspectRatio();
            })->save($savePath);
    }

    // Delete File
    public function deleteFile($path)
    {
        if(file_exists($path)) {
            @unlink($path);
        }
    }
}
