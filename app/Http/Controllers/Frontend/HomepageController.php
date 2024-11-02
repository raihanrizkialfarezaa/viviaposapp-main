<?php

namespace App\Http\Controllers\Frontend;

use App\Models\Slide;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\ProductCategory;
use Gloudemans\Shoppingcart\Facades\Cart;

class HomepageController extends Controller
{

    public function index()
    {
        // dd(public_path());
        $productActive = Product::where('type', 'simple')->get()->pluck('id');
        $productActives = array($productActive);
        $products = ProductCategory::with('categories', 'products')->limit(8)->whereIn('product_id', $productActives[0])->get();
        // dd($products);
        $categories = ProductCategory::with('products', 'categories')->whereIn('product_id', $productActives[0])->get();
        // $products = Product::all();
        $popular = Product::where('type', 'simple')->active()->limit(6)->get();
        $totalProduct = Product::where('type', 'simple')->count();
        // $productsImage = $products[4]->products->productImages;
        // dd($productsImage);
        // dd($products->categories);
        $slides = Slide::active()->orderBy('position', 'ASC')->get();
        // dd(Cart::content()->count());
        $cart = Cart::content()->count();
		view()->share('countCart', $cart);
        // $cart = Cart::content()->count();
        // view()->share('countCart', $cart);
        return view('frontend.homepage', compact('products', 'totalProduct', 'categories', 'popular', 'slides'));
    }

    public function detail($id)
    {
        $product = ProductCategory::where('product_id', $id)->with('categories', 'products')->first();
        $products = ProductCategory::with('categories', 'products')->get();
        $cart = Cart::content()->count();
		view()->share('countCart', $cart);
        // dd($product->products->productImages);
        return view('frontend.shop.detail', compact('product', 'products'));
    }

    public function shop(Request $request)
    {
        $produk = Product::where('type', 'simple')->get()->pluck('id');
        $produkss = array($produk);
        // dd($produkss);
        $products = ProductCategory::with(['products', 'categories'])->whereIn('product_id', $produkss[0])->get();
        // dd($products);
        $producteds = ProductCategory::with(['products', 'categories'])->whereIn('product_id', $produkss[0])->get();
        $cart = Cart::content()->count();
		view()->share('countCart', $cart);
        $categories = Category::all();
        
        if (count($products) <= 1) {
            if (request()->has('search')) {
                // dd($products[0]->products);
                $products = $products[0]->products->where('name', 'like', '%' . request()->get('search', ''). '%')->where('type', 'simple')->first();
                // dd($products);
                $product = Product::where('id', $products->id)->first();
                $products->productImages = $product->productImages;
                // dd(count($products->categories));
            }
        } else {
            if (request()->has('search')) {
                // dd($products[0]->products);
                foreach ($products as $row) {
                    $products = $row->products->where('name', 'like', '%' . request()->get('search', ''). '%')->where('type', 'simple')->get();
                    $product = Product::where('id', $row->products->id)->first();
                    $products->productImages = $product->productImages;
                    // dd($products[0]);
                }
            }
        }

        if (request()->has('search')) {
            $producted = $products;
        } else {
            $producted = $producteds;
        }
            // dd($producted[0]->productInventory);
        

        return view('frontend.shop.index', [
            'products' => $producted,
            'categories' => $categories,
        ]);
    }
    public function shopCetak(Request $request)
    {
        $cat = Category::where('slug', 'like', '%' . 'cetak' . '%')->get()->pluck('id');
        $cats = array($cat);
        // dd($cats[0][0]);
        $products = ProductCategory::with(['products', 'categories'])->whereIn('category_id', $cats[0])->get();
        // dd($products);
        $producteds = ProductCategory::with(['products', 'categories'])->whereIn('category_id', $cats[0])->get();
        $cart = Cart::content()->count();
		view()->share('countCart', $cart);
        $categories = Category::all();
        
        if (count($products) <= 1) {
            if (request()->has('search')) {
                // dd($products[0]->products);
                $products = $products[0]->products->where('name', 'like', '%' . request()->get('search', ''). '%')->first();
                // dd($products);
                $product = Product::where('id', $products->id)->first();
                $products->productImages = $product->productImages;
                // dd(count($products->categories));
            }
        } else {
            if (request()->has('search')) {
                // dd($products[0]->products);
                foreach ($products as $row) {
                    $products = $row->products->where('name', 'like', '%' . request()->get('search', ''). '%')->get();
                    $product = Product::where('id', $row->products->id)->first();
                    $products->productImages = $product->productImages;
                }
            }
        }

        if (request()->has('search')) {
            $producted = $products;
        } else {
            $producted = $producteds;
        }
        

        return view('frontend.shop.index', [
            'products' => $producted,
            'categories' => $categories,
        ]);
    }
}
