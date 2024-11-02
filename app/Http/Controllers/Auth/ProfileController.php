<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index()
	{
		$user = auth()->user();
		// dd($user);
        $provinces = $this->getProvinces();
        $cities = isset($user->province_id) ? $this->getCities($user->province_id) : [];
		$cart = Cart::content()->count();
		view()->share('countCart', $cart);
        
		return view('frontend.auth.profile', compact('user','provinces','cities'));
    }
    
    public function update(Request $request)
	{
		$params = $request->except('_token');
		// dd($params);
		$user = auth()->user();
		$user->update([
			'first_name' => $params['first_name'],
			'last_name' => $params['last_name'],
			'email' => $params['email'],
			'province_id' => $params['province_id'],
			'city_id' => $params['city_id'],
			'postcode' => $params['postcode'],
			'address1' => $params['address1'],
			'address2' => $params['address2'],
			'phone' => $params['phone'],
		]);
		if ($user) {
			// dd($user);
			return redirect('profile');
		}
	}
}