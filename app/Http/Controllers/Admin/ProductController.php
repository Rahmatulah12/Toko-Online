<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Requests\ProductRequest;
use App\Http\Requests\ProductImageRequest;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Helpers\CollectionPaginate;
use App\Helpers\General;

class ProductController extends Controller
{
    private $product;

    private $category;

    public function __construct()
    {
        $this->product = new Product();
        $this->category = new Category();

        $this->data['statuses'] = $this->product->statuses();
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $this->data['products'] = $this->product->getAllData();
        return view('admins.products.index', $this->data);
    }

    public function search(Request $request)
    {
        $filter = General::sanitasiInputString($request->input());
        $this->data['products'] = $this->product->getAllData($filter['keyword'], 
            (int) $filter['size']);
        
        return view('admins.products.search', $this->data);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $this->data['product'] = NULL;
        $this->data['categories'] = $this->category->orderBy('name', 'ASC')->get()->toArray();
        $this->data['sku'] = $this->product->generateSKU();
        $this->data['categoryIDs'] = [];
        $this->data['productId'] = null;
        return view('admins.products.form', $this->data);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(ProductRequest $request)
    {   
        $data = $request->except('_token');
        $data['slug'] = Str::slug($data['name']);
        $data['user_id'] = auth()->user()->id;
        
        // Format Price
        $format1 = explode(" ", $data['price']);
        $format2 = explode(",", $format1[1]);
        $format3 = implode("", $format2);
        $data['price'] = (int) $format3;        
        // End Of Format Price

        $data['weight'] = (double)$data['weight'];
        $data['height'] = (double)$data['height'];
        $data['width'] = (double)$data['width'];
        $data['length'] = (double)$data['length'];
        $saved = false;
        $saved = DB::transaction(function () use($data) {
            $product = $this->product->create($data);
            $product->categories()->sync($data['category_ids']);
            return true;
        });
        if(!$saved){
            return redirect()->back()->with(["error" => "Failed to saved data"]);
        } else {
            return redirect("admin/master/product")->with(['message' => 'Data has been saved']);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if(empty($id)){
            return redirect('admin/master/product/create');
        }
        $this->data['product'] = $this->product->findOrFail($id);
        $this->data['categories'] = $this->category->orderBy('name', 'ASC')->get()->toArray();
        $this->data['categoryIDs'] = $this->data['product']->pluck('id')->toArray();
        $this->data['productId'] = (int)$this->data['product']->id;
        return view('admins.products.form', $this->data);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(ProductRequest $request, $id)
    {
        $product = $this->product->findOrFail($id);
        $data = $request->except('_token');
        $data['slug'] = Str::slug($data['name']);
        $data['user_id'] = auth()->user()->id;
        // Format Price
        $idr = "IDR";
        if(preg_match("/$idr/i", $data["price"]))
        {
            
            $format1 = explode(" ", $data['price']);
            $format2 = explode(",", $format1[1]);
            $format3 = implode("", $format2);
            $data['price'] = (int) $format3;   
            
        } else {
            $data['price'] = (int)$data['price'];
        }
        // End Of Format Price
        $data['weight'] = (double)$data['weight'];
        $data['height'] = (double)$data['height'];
        $data['width'] = (double)$data['width'];
        $data['length'] = (double)$data['length'];  
        $saved = false;
        $saved = DB::transaction(function () use($product, $data) {
            $product->update($data);
            $product->categories()->sync($data['category_ids']);
            return true;
        });
        if(!$saved){
            return redirect()->back()->with(["error" => "Failed to saved data"]);
        } else {
            return redirect("admin/master/product")->with(['message' => 'Data has been saved']);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $product = $this->product->findOrFail($id);
        $deleteProduct = $this->product->destroy($id);
        if(!$deleteProduct)
        {
            return false;
        }
        return response("ok");
    }

    /**
     *  
     * @author @Rahmatulah Sidik
     * function for images list product by product id
     */
    public function images($productId)
    {
        if(empty($productId)){
            return redirect('admin/master/product/create');
        }
        $this->data['product'] = $this->product->findOrFail($productId);
        $this->data['productId'] = $this->data['product']->id;
        $productImages = $this->data['product']->productImages;
        // implement helper CollectionPaginate
        $this->data['productImages'] = CollectionPaginate::paginate($productImages, 2);
        
        return view('admins.products.images', $this->data);
    }

    /**
     * 
     *  @author Rahmatulah Sidik
     * this function for add new Images Form
     */
    public function addImages($productId)
    {
        // jika product id tidak ada
        if(!isset($productId) || empty($productId))
        {
            return redirect('admin/master/product');
        }

        // find one product by id
        $product = $this->product->findOrFail($productId);

        $this->data['productId'] = $product->id;
        $this->data['product'] = $product;

        return view('admins.products.image_form', $this->data);

    }

    public function uploadImages(ProductImageRequest $request, $productId)
    {
        $product = $this->product->findOrFail($productId);
        
        // cek ada file image gak
        if($request->has('image'))
        {
            $images = $request->image;
            $i = 0;
            foreach($images as $image)
            {
                $i++;
                $save = false;
                // dd($image->storeAs());
                // $imageData = $image->file('image');
                $name = $product->slug . "($i)" . "_" . time();
                $fileName = $name . '.' . $image->getClientOriginalExtension();

                $folder = '/uploads/product_images';
                $filePath = $image->storeAs($folder, $fileName, 'public');
                
                $params = [
                    "product_id" => $product->id,
                    "path" => $filePath,
                    "created_at" => date("Y-m-d H:i:s"),
                    "updated_at" => date("Y-m-d H:i:s"),
                ];

                $save = DB::transaction(function() use($params) {
                    ProductImage::create($params);
                    return true;
                });

                if(!$save){
                    break;
                    return redirect()->back()->with(["error" => "Failed to saved image"]);
                }
            }

        }
        return redirect("admin/master/product")->with(['message' => 'Image has been saved']);
    }

    /**
     * @author Rahmatulah Sidik
     * this function for store upload images product
     */

    /**
     * @Author Rahmatulah Sidik
     */
    public function destroyImages($imageId)
    {
        $image = ProductImage::findOrFail($imageId);
        // delete file
        $basePath = Storage::delete("/public/$image->path");
        
        if($image->delete())
        {
            return redirect("admin/master/product")->with(['message' => 'Image has been deleted.']);
        } else {
            return redirect()->back()->with(["error" => "Failed to delete image"]);
        }
    }
}