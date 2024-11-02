<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Support\Facades\Hash;
use App\Http\Requests\ProfileUpdateRequest;
use App\Http\Controllers\Controller;
use Gloudemans\Shoppingcart\Facades\Cart;

class ProfileController extends Controller
{
    public function show()
    {
        $cart = Cart::content()->count();
		view()->share('countCart', $cart);
        return view('auth.profile');
    }

    public function update(ProfileUpdateRequest $request)
    {
        if ($request->password) {
            auth()->user()->update(['password' => Hash::make($request->password)]);
        }

        // dd($request);
        auth()->user()->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'province_id' => $request->province_id,
            'city_id' => $request->city_id,
            'postcode' => $request->postcode,
        ]);

        return redirect()->route('admin.profile.show')->with([
            'message' => 'berhasil di ubah !'
        ]);
    }
}
