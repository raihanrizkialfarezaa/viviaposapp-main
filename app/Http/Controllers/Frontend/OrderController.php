<?php

namespace App\Http\Controllers\Frontend;

use App\Models\Order;
use App\Models\Payment;
use App\Models\Shipment;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use App\Models\ProductInventory;
use App\Http\Controllers\Controller;
use Gloudemans\Shoppingcart\Facades\Cart;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
	public function index()
	{
		$orders = Order::forUser(auth()->user())
			->orderBy('created_at', 'DESC')
			->with(['shipment'])
			->get();
		// dd($orders[0]->shipment);
		$cart = Cart::content()->count();
		view()->share('countCart', $cart);

		return view('frontend.orders.index', compact('orders'));
	}

	public function show($id)
	{
		$order = Order::forUser(auth()->user())->findOrFail($id);
		$cart = Cart::content()->count();
		view()->share('countCart', $cart);
		return view('frontend.orders.show',compact('order'));
	}
	
	private function _getTotalWeight()
	{
		if (Cart::count() <= 0) {
			return 0;
		}

		$totalWeight = 0;

		$items = Cart::content();

		foreach ($items as $item) {
			$totalWeight += ($item->qty * $item->model->weight);
		}

		return $totalWeight;
	}

	public function cities(Request $request)
	{
		$cities = $this->getCities($request->query('province_id'));
		return response()->json(['cities' => $cities]);
	}

	public function shippingCost(Request $request)
	{
		$destination = $request->input('city_id');
		
		return $this->_getShippingCost($destination, $this->_getTotalWeight());
	}

	private function _getShippingCost($destination, $weight)
	{
		if (request()->get('shipping_service') == 'SELF') {
			$params = [
				'origin' => $this->rajaOngkirOrigin,
				'destination' => $destination,
				'weight' => $weight,
			];
	
			$results = [];
			$result = [
				'service' => 'SelfTake',
				'cost' => 0,
				'etd' => 'Today',
				'courier' => 'SELF',
			];
			$results[] = $result;
		} else {
			$params = [
				'origin' => $this->rajaOngkirOrigin,
				'destination' => $destination,
				'weight' => $weight,
			];
	
			$results = [];
			foreach ($this->couriers as $code => $courier) {
				$params['courier'] = $code;
	
				$response = $this->rajaOngkirRequest('/cost', $params, 'POST');
	
				if (!empty($response['rajaongkir']['results'])) {
					foreach ($response['rajaongkir']['results'] as $cost) {
						if (!empty($cost['costs'])) {
							foreach ($cost['costs'] as $costDetail) {
								$serviceName = strtoupper($cost['code']) .' - '. $costDetail['service'];
								$costAmount = $costDetail['cost'][0]['value'];
								$etd = $costDetail['cost'][0]['etd'];
	
								$result = [
									'service' => $serviceName,
									'cost' => $costAmount,
									'etd' => $etd,
									'courier' => $code,
								];
	
								$results[] = $result;
							}
						}
					}
				}
			}
		}
		
		

		$response = [
			'origin' => $params['origin'],
			'destination' => $destination,
			'weight' => $weight,
			'results' => $results,
		];
		
		return $response;
	}

	public function confirmPayment(Request $request, $id)
	{
		$order = Order::where('id', $id)->first();

		$order->update([
			'payment_slip' => $request->file('file_bukti')->store('assets/bukti_pembayaran', 'public'),
			'payment_status' => Order::WAITING,
		]);

		return redirect()->route('showUsersOrder', $id);
	}

	public function setShipping(Request $request)
	{
		$shippingService = $request->get('shipping_service');
		$destination = $request->get('city_id');

		$shippingOptions = $this->_getShippingCost($destination, $this->_getTotalWeight());
		// dd($shippingOptions);
		
		$selectedShipping = null;
		// dd(count($shippingOptions['results']));
		if (count($shippingOptions['results']) <= 1) {
			$selectedShipping = $shippingOptions['results'][0];
		} elseif(count($shippingOptions['results']) > 1) {
			foreach ($shippingOptions['results'] as $shippingOption) {
				if (str_replace(' ', '', $shippingOption['service']) == $shippingService) {
					$selectedShipping = $shippingOption;
					break;
				}
			}
		}

		$status = null;
		$message = null;
		$data = [];
		if ($selectedShipping) {
			$status = 200;
			$message = 'Success set shipping cost';
			$data['total'] = (int)Cart::subtotal(0,'','') + $selectedShipping['cost'];
		} else {
			$status = 400;
			$message = 'Failed to set shipping cost';
		}

		$response = [
			'status' => $status,
			'message' => $message
		];
		// dd($response);

		if ($data) {
			$response['data'] = $data;
		}

		return $response;
	}

    public function checkout()
    {
		$cart = Cart::content()->count();
		view()->share('countCart', $cart);
        if (Cart::count() == 0) {
			return redirect('carts');
		}

		$items = Cart::content();

		$unique_code = random_int('1', '999');
		// dd($unique_code);

		$totalWeight = $this->_getTotalWeight() / 1000;

		$provinces = $this->getProvinces();
		
		$cities = isset(auth()->user()->province_id) ? $this->getCities(auth()->user()->province_id) : [];
        
		return view('frontend.orders.checkout', compact('items', 'unique_code', 'totalWeight','provinces','cities'));
	}

	public function doCheckout(Request $request)
    {
        $params = $request->except('_token');
		$params['attachments'] = $request->file('attachments');

		$order = DB::transaction(
			function () use ($params) {
				$order = $this->_saveOrder($params);
				$this->_saveOrderItems($order);
				if ($params['payment_method'] == 'automatic') {
					$this->_generatePaymentToken($order);
				}
				
				$this->_saveShipment($order, $params);
	
				return $order;
			}
		);

		if ($order) {
			Cart::destroy();
			// $this->_sendEmailOrderReceived($order);

			Session::flash('success', 'Thank you. Your order has been received!');
			return redirect('orders/received/'. $order->id);
		}

		return redirect()->back();
    }
	
	private function _getSelectedShipping($destination, $totalWeight, $shippingService)
	{
		$shippingOptions = $this->_getShippingCost($destination, $totalWeight);

		$selectedShipping = null;
		if (count($shippingOptions['results']) <= 1) {
			$selectedShipping = $shippingOptions['results'][0];
		} elseif(count($shippingOptions['results']) > 1) {
			foreach ($shippingOptions['results'] as $shippingOption) {
				if (str_replace(' ', '', $shippingOption['service']) == $shippingService) {
					$selectedShipping = $shippingOption;
					break;
				}
			}
		}

		return $selectedShipping;
	}

	public function downloadFile($id)
	{
		$order = Order::find($id);

		return Storage::download('/' . $order->attachments);
	}

    private function _saveOrder($params)
	{
		$destination = !isset($params['ship_to']) ? $params['shipping_city_id'] : $params['customer_shipping_city_id'];
		$selectedShipping = $this->_getSelectedShipping($destination, $this->_getTotalWeight(), $params['shipping_service']);
		
		$baseTotalPrice = (int)Cart::subtotal(0,'','');
		$taxAmount = 0;
		$taxPercent = 0;
		$shippingCost = $selectedShipping['cost'];
		// dd($params);
		$discountAmount = 0;
		if ($params['payment_method'] == 'manual') {
			$paymentMethod = 'manual';
		} elseif($params['payment_method'] == 'automatic') {
			$paymentMethod = 'automatic';
		} elseif($params['payment_method'] == 'qris') {
			$paymentMethod = 'qris';
		} else {
			$paymentMethod = 'cod';
		}
		$unique_code = $params['unique_code'];
		$discountPercent = 0;
		$grandTotal = ($baseTotalPrice + $taxAmount + $shippingCost) - $discountAmount + $unique_code;

		$orderDate = date('Y-m-d H:i:s');
		$paymentDue = (new \DateTime($orderDate))->modify('+7 day')->format('Y-m-d H:i:s');

		$user_profile = [
			'first_name' => $params['first_name'],
			'last_name' => $params['last_name'],
			'address1' => $params['address1'],
			'address2' => $params['address2'],
			'province_id' => $params['province_id'],
			'city_id' => $params['shipping_city_id'],
			'postcode' => $params['postcode'],
			'phone' => $params['phone'],
			'email' => $params['email'],
		];

		auth()->user()->update($user_profile);

		if ($params['attachments'] != null) {
			$orderParams = [
				'user_id' => auth()->id(),
				'code' => Order::generateCode(),
				'status' => Order::CREATED,
				'order_date' => $orderDate,
				'payment_due' => $paymentDue,
				'payment_status' => Order::UNPAID,
				'attachments' => $params['attachments']->store('assets/slides', 'public'),
				'base_total_price' => $baseTotalPrice,
				'tax_amount' => $taxAmount,
				'tax_percent' => $taxPercent,
				'discount_amount' => $discountAmount,
				'discount_percent' => $discountPercent,
				'shipping_cost' => $shippingCost,
				'grand_total' => $grandTotal,
				'note' => $params['note'],
				'customer_first_name' => $params['first_name'],
				'customer_last_name' => $params['last_name'],
				'customer_address1' => $params['address1'],
				'payment_method' => $paymentMethod,
				'customer_address2' => $params['address2'],
				'customer_phone' => $params['phone'],
				'customer_email' => $params['email'],
				'customer_city_id' => $params['shipping_city_id'],
				'customer_province_id' => $params['province_id'],
				'customer_postcode' => $params['postcode'],
				'shipping_courier' => $selectedShipping['courier'],
				'shipping_service_name' => $selectedShipping['service'],
			];
		} else {
			$orderParams = [
				'user_id' => auth()->id(),
				'code' => Order::generateCode(),
				'status' => Order::CREATED,
				'order_date' => $orderDate,
				'payment_due' => $paymentDue,
				'payment_status' => Order::UNPAID,
				'base_total_price' => $baseTotalPrice,
				'tax_amount' => $taxAmount,
				'tax_percent' => $taxPercent,
				'discount_amount' => $discountAmount,
				'discount_percent' => $discountPercent,
				'shipping_cost' => $shippingCost,
				'grand_total' => $grandTotal,
				'note' => $params['note'],
				'customer_first_name' => $params['first_name'],
				'customer_last_name' => $params['last_name'],
				'customer_address1' => $params['address1'],
				'payment_method' => $paymentMethod,
				'customer_address2' => $params['address2'],
				'customer_phone' => $params['phone'],
				'customer_email' => $params['email'],
				'customer_city_id' => $params['shipping_city_id'],
				'customer_province_id' => $params['province_id'],
				'customer_postcode' => $params['postcode'],
				'shipping_courier' => $selectedShipping['courier'],
				'shipping_service_name' => $selectedShipping['service'],
			];
		}
		

		return Order::create($orderParams);
	}

	private function _saveOrderItems($order)
	{
		$cartItems = Cart::content();

		if ($order && $cartItems) {
			foreach ($cartItems as $item) {
				$itemTaxAmount = 0;
				$itemTaxPercent = 0;
				$itemDiscountAmount = 0;
				$itemDiscountPercent = 0;
				$itemBaseTotal = $item->qty * $item->price;
				$itemSubTotal = $itemBaseTotal + $itemTaxAmount - $itemDiscountAmount;

				$product = isset($item->model->parent) ? $item->model->parent : $item->model;

				$orderItemParams = [
					'order_id' => $order->id,
					'product_id' => $item->model->id,
					'qty' => $item->qty,
					'base_price' => $item->price,
					'base_total' => $itemBaseTotal,
					'tax_amount' => $itemTaxAmount,
					'tax_percent' => $itemTaxPercent,
					'discount_amount' => $itemDiscountAmount,
					// 'attachments' => $order->
					'discount_percent' => $itemDiscountPercent,
					'sub_total' => $itemSubTotal,
					'sku' => $item->model->sku,
					'type' => $product->type,
					'name' => $item->name,
					'weight' => $item->model->weight,
					'attributes' => json_encode($item->options),
				];

				$orderItem = OrderItem::create($orderItemParams);
				
				if ($orderItem) {
					ProductInventory::reduceStock($orderItem->product_id, $orderItem->qty);
				}
			}
		}
	}

	public function confirmPaymentManual($id) {
		$order = Order::where('id', $id)->first();
		if ($order->payment_status != 'unpaid') {
			return redirect('profile');
		} else {
			$cart = Cart::content()->count();
			view()->share('countCart', $cart);
			return view('admin.orders.confirmPayment', compact('order'));
		}
		
		
	}

	public function confirmPaymentAdmin($id)
	{
		$order = Order::where('id', $id)->first();
		$order->update([
			'payment_status' => Order::PAID,
			'status' => Order::CONFIRMED,
		]);
		$cart = Cart::content()->count();
		view()->share('countCart', $cart);
		return redirect()->route('admin.orders.show', $id);
	}

	private function _generatePaymentToken($order)
	{
		$this->initPaymentGateway();

		$customerDetails = [
			'first_name' => $order->customer_first_name,
			'last_name' => $order->customer_last_name,
			'email' => $order->customer_email,
			'phone' => $order->customer_phone,
		];

		$params = [
			'enable_payments' => Payment::PAYMENT_CHANNELS,
			'transaction_details' => [
				'order_id' => $order->code,
				'gross_amount' => $order->grand_total,
			],
			'customer_details' => $customerDetails,
			'expiry' => [
				'start_time' => date('Y-m-d H:i:s T'),
				'unit' => Payment::EXPIRY_UNIT,
				'duration' => Payment::EXPIRY_DURATION,
			],
		];

		$snap = \Midtrans\Snap::createTransaction($params);
		
		if ($snap->token) {
			$order->payment_token = $snap->token;
			$order->payment_url = $snap->redirect_url;
			$order->save();
		}
	}

	private function _saveShipment($order, $params)
	{
		$shippingFirstName = isset($params['ship_to']) ? $params['shipping_first_name'] : $params['first_name'];
		$shippingLastName = isset($params['ship_to']) ? $params['shipping_last_name'] : $params['last_name'];
		$shippingAddress1 = isset($params['ship_to']) ? $params['shipping_address1'] : $params['address1'];
		$shippingAddress2 = isset($params['ship_to']) ? $params['shipping_address2'] : $params['address2'];
		$shippingPhone = isset($params['ship_to']) ? $params['shipping_phone'] : $params['phone'];
		$shippingEmail = isset($params['ship_to']) ? $params['shipping_email'] : $params['email'];
		$shippingCityId = isset($params['ship_to']) ? $params['shipping_city_id'] : $params['shipping_city_id'];
		$shippingProvinceId = isset($params['ship_to']) ? $params['shipping_province_id'] : $params['province_id'];
		$shippingPostcode = isset($params['ship_to']) ? $params['shipping_postcode'] : $params['postcode'];
		$totalQty = 0;
		foreach($order->orderItems as $orderItem) {
			$totalQty += $orderItem->qty;
		}	

		$shipmentParams = [
			'user_id' => auth()->id(),
			'order_id' => $order->id,
			'status' => Shipment::PENDING,
			'total_qty' => $totalQty,
			'total_weight' => $this->_getTotalWeight(),
			'first_name' => $shippingFirstName,
			'last_name' => $shippingLastName,
			'address1' => $shippingAddress1,
			'address2' => $shippingAddress2,
			'phone' => $shippingPhone,
			'email' => $shippingEmail,
			'city_id' => $shippingCityId,
			'province_id' => $shippingProvinceId,
			'postcode' => $shippingPostcode,
		];

		Shipment::create($shipmentParams);
	}

	public function received($orderId)
	{
		$order = Order::where('id', $orderId)
			->where('user_id', auth()->id())
			->firstOrFail();
			$cart = Cart::content()->count();
			view()->share('countCart', $cart);

		return view('frontend.orders.received', compact('order'));
	}

}
