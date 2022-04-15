<?php

namespace App\Models;

use App\Traits\Helpers;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductGallery extends Model
{
    use HasFactory, Helpers;

    protected $table = "product_galleries";

    // List all the image sizes
    protected $appends = ["image_url"];

    // Generate Image Url
    public function getImageUrlAttribute()
    {
        $urls = [];
        foreach ($this->imagesSizes as $dirName => $imagesSize) {
            $urls[$dirName] = url('/') . '/uploads/' . $this->product_id . '/' . $dirName . '/' . $this->image;
        }
        return $urls;
    }
}
