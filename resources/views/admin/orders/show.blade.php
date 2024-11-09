@extends('layouts.app')

@section('content')

<section class="content pt-4">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">

            <div class="card">
              <div class="card-header">
                <h2 class="text-dark font-weight-medium">Order ID #{{ $order->code }}</h2>
                @if ($order->attachments != null)
							<a href="{{ asset('/storage/' . $order->attachments) }}" class="btn btn-primary">See attachments</a>
						@endif
                <div class="btn-group float-right">
                </div>
              </div>
              <!-- /.card-header -->
              <div class="card-body">
                <div class="row pt-2 mb-3">
                    <div class="col-lg-4">
                        <p class="text-dark" style="font-weight: normal; font-size:16px; text-transform: uppercase;">Billing Address</p>
                        <address>
                            {{ $order->customer_full_name }}
                             {{ $order->customer_address1 }}
                             {{ $order->customer_address2 }}
                            <br> Email: {{ $order->customer_email }}
                            <br> Phone: {{ $order->customer_phone }}
                            <br> Postcode: {{ $order->customer_postcode }}
                        </address>
                    </div>
                    <div class="col-lg-4">
                        <p class="text-dark" style="font-weight: normal; font-size:16px; text-transform: uppercase;">Shipment Address</p>
                        @if ($order->shipment != null)
                            <address>
                                {{ $order->shipment->first_name }} {{ $order->shipment->last_name }}
                                    {{ $order->shipment->address1 }}
                                    {{ $order->shipment->address2 }}
                                <br> Email: {{ $order->shipment->email }}
                                <br> Phone: {{ $order->shipment->phone }}
                                <br> Postcode: {{ $order->shipment->postcode }}
                            </address>
                        @else
                            <address>
                            <br> Ambil di Toko
                        </address>
                        @endif
                    </div>
                    <div class="col-lg-4">
                        <p class="text-dark mb-2" style="font-weight: normal; font-size:16px; text-transform: uppercase;">Details</p>
                        <address>
                            ID: <span class="text-dark">#{{ $order->code }}</span>
                            <br> {{ $order->order_date }}
                            <br> Status: {{ $order->status }} {{ $order->isCancelled() ? '('. $order->cancelled_at .')' : null}}
                            @if ($order->isCancelled())
                                <br> Cancellation Note : {{ $order->cancellation_note}}
                            @endif
                            <br> Payment Status: {{ $order->payment_status }}
                            <br> Payment Method: {{ $order->payment_method }}
                            <br> Shipped by: {{ $order->shipping_service_name }}
                        </address>
                    </div>
                </div>
                <div class="table-responsive">
                    <table id="data-table" class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Item</th>
                                <th>Description</th>
                                <th>Quantity</th>
                                <th>Unit Cost</th>
                                <th>Total</th>
                                <th>Catatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                function showAttributes($jsonAttributes)
                                {
                                    $jsonAttr = (string) $jsonAttributes;
                                    $attributes = json_decode($jsonAttr, true);
                                    $showAttributes = '';
                                    if ($attributes) {
                                        $showAttributes .= '<ul class="item-attributes list-unstyled">';
                                        foreach ($attributes as $key => $attribute) {
                                            if(count($attribute) != 0){
                                                foreach($attribute as $value => $attr){
                                                    $showAttributes .= '<li>'.$value . ': <span>' . $attr . '</span><li>';
                                                }
                                            }else {
                                                $showAttributes .= '<li><span> - </span></li>';
                                            }
                                        }
                                        $showAttributes .= '</ul>';
                                    }
                                    return $showAttributes;
                                }
                            @endphp
                            @forelse ($order->orderItems as $item)
                                <tr>
                                    <td>{{ $item->sku }}</td>
                                    <td>{{ $item->name }}</td>
                                    <td>{!! showAttributes($item->attributes) !!}</td>
                                    <td>{{ $item->qty }}</td>
                                    <td>Rp{{ number_format($item->base_price,0,",",".") }}</td>
                                    <td>Rp{{ number_format($item->sub_total,0,",",".") }}</td>
                                    <td>{{ $order->note }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">Order item not found!</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    <div class="row ">
                        @if ($order->payment_method == 'manual' || $order->payment_method == 'qris')
                            <div class="col-lg-6 justify-content-start col-xl-4 col-xl-3 ml-sm-auto pb-4">
                                <h4>Payment Slip :</h4>
                                <br>
                                <img  src="{{ asset('/storage/' . $order->payment_slip ) }}" width="600" alt="">
                            </div>
                        @endif
                        <div class="col-lg-5 justify-content-end col-xl-4 col-xl-3 ml-sm-auto pb-4">
                            <ul class="list-unstyled mt-4">
                                <li class="mid pb-3 text-dark">Subtotal
                                    <span class="d-inline-block float-right text-default">Rp{{ number_format($order->base_total_price,0,",",".") }}</span>
                                </li>
                                <li class="mid pb-3 text-dark">Tax(10%)
                                    <span class="d-inline-block float-right text-default">Rp{{ number_format($order->tax_amount,0,",",".") }}</span>
                                </li>
                                <li class="mid pb-3 text-dark">Shipping Cost
                                    <span class="d-inline-block float-right text-default">Rp{{ number_format($order->shipping_cost,0,",",".") }}</span>
                                </li>
                                <li class="pb-3 text-dark">Unique Code
                                    <span class="d-inline-block float-right">Rp{{ number_format(($order->grand_total - ($order->base_total_price + $order->shipping_cost)),0,",",".") }}</span>
                                </li>
                                <li class="pb-3 text-dark">Total
                                    <span class="d-inline-block float-right">Rp{{ number_format($order->grand_total,0,",",".") }}</span>
                                </li>
                            </ul>
                            @if (!$order->trashed())
                                    @if ($order->isPaid() && $order->isConfirmed() && $order->payment_method != 'cod' && $order->payment_method != 'toko')
                                        <a href="{{ url('admin/shipments/'. $order->shipment->id .'/edit')}}" class="btn btn-block mt-2 btn-lg btn-primary btn-pill"> Procced to Shipment</a>
                                    @elseif($order->isPaid() && $order->isConfirmed() && $order->payment_method == 'cod')
                                        <a href="#" class="btn btn-block mt-2 btn-lg btn-success btn-pill" onclick="event.preventDefault();
                                        document.getElementById('complete-form-{{ $order->id }}').submit();"> Mark as Completed</a>
                                        <form class="d-none" method="POST" action="{{ route('admin.orders.complete', $order) }}" id="complete-form-{{ $order->id }}">
                                            @csrf
                                        </form>
                                    @endif

                                    @if (in_array($order->status, [\App\Models\Order::CREATED, \App\Models\Order::CONFIRMED]) && $order->payment_method == 'automatic')
                                        <a href="{{ url('admin/orders/'. $order->id .'/cancel')}}" class="btn btn-block mt-2 btn-lg btn-warning btn-pill"> Cancel</a>

                                    @elseif (in_array($order->status, [\App\Models\Order::CREATED, \App\Models\Order::CONFIRMED]) && $order->payment_method == 'manual' || $order->payment_method == 'cod' || $order->payment_method == 'qris' && $order->isPaid())
                                    <a href="{{ url('admin/orders/'. $order->id .'/cancel')}}" class="btn btn-block mt-2 btn-lg btn-warning btn-pill"> Cancel</a>
                                    @elseif (in_array($order->status, [\App\Models\Order::CREATED, \App\Models\Order::CONFIRMED]) && $order->payment_method == 'manual' || $order->payment_method == 'cod' || $order->payment_method == 'qris' && !$order->isPaid())
                                    <a href="{{ url('admin/orders/'. $order->id .'/cancel')}}" class="btn btn-block mt-2 btn-lg btn-warning btn-pill"> Cancel</a>

                                    @endif
                                    @if ($order->payment_status == 'waiting' && $order->payment_method == 'qris')
                                            <form action="{{ route('admin.orders.confirmAdmin', $order->id) }}" method="POST">
                                                @method('PUT')
                                                @csrf
                                                <button type="submit" class="btn btn-block mt-2 btn-lg btn-success btn-pill"> Confirm Payment</button>
                                            </form>
                                    @elseif ($order->payment_status == 'waiting' && $order->payment_method == 'manual')
                                            <form action="{{ route('admin.orders.confirmAdmin', $order->id) }}" method="POST">
                                                @method('PUT')
                                                @csrf
                                                <button type="submit" class="btn btn-block mt-2 btn-lg btn-success btn-pill"> Confirm Payment</button>
                                            </form>
                                    @elseif($order->payment_status == 'unpaid' && $order->payment_method == 'manual')
                                        <form action="{{ route('admin.orders.confirmAdmin', $order->id) }}" method="POST">
                                            @method('PUT')
                                            @csrf
                                            <button type="submit" class="btn btn-block mt-2 btn-lg btn-success btn-pill"> Confirm Payment</button>
                                        </form>
                                    @elseif($order->payment_status == 'unpaid' && $order->payment_method == 'cod')
                                        <form action="{{ route('admin.orders.confirmAdmin', $order->id) }}" method="POST">
                                            @method('PUT')
                                            @csrf
                                            <button type="submit" class="btn btn-block mt-2 btn-lg btn-success btn-pill"> Confirm Payment</button>
                                        </form>
                                    @elseif($order->payment_status == 'unpaid' && $order->payment_method == 'qris')
                                        <form action="{{ route('admin.orders.confirmAdmin', $order->id) }}" method="POST">
                                            @method('PUT')
                                            @csrf
                                            <button type="submit" class="btn btn-block mt-2 btn-lg btn-success btn-pill"> Confirm Payment</button>
                                        </form>
                                    @elseif($order->payment_status == 'unpaid' && $order->payment_method == 'toko')
                                        <form action="{{ route('admin.orders.confirmAdmin', $order->id) }}" method="POST">
                                            @method('PUT')
                                            @csrf
                                            <button type="submit" class="btn btn-block mt-2 btn-lg btn-success btn-pill"> Confirm Payment</button>
                                        </form>
                                    @endif
                                    @if ($order->isDelivered())
                                        <a href="#" class="btn btn-block mt-2 btn-lg btn-success btn-pill" onclick="event.preventDefault();
                                        document.getElementById('complete-form-{{ $order->id }}').submit();"> Mark as Completed</a>
                                        <form class="d-none" method="POST" action="{{ route('admin.orders.complete', $order) }}" id="complete-form-{{ $order->id }}">
                                            @csrf
                                        </form>
                                    @endif

                                    @if (!in_array($order->status, [\App\Models\Order::DELIVERED, \App\Models\Order::COMPLETED]))
                                        <a href="#" class="btn btn-block mt-2 btn-lg btn-secondary btn-pill delete" order-id="{{ $order->id }}"> Remove</a>
                                        <form action="{{ route('admin.orders.destroy',$order) }}" method="post" id="delete-form-{{ $order->id }}" class="d-none">
                                            @csrf
                                            @method('delete')
                                        </form>
                                    @endif
                                @else
                                    <a href="{{ url('admin/orders/restore/'. $order->id)}}" class="btn btn-block mt-2 btn-lg btn-outline-secondary btn-pill restore">Restore</a>
                                    <a href="#" class="btn btn-block mt-2 btn-lg btn-danger btn-pill delete" order-id="{{ $order->id }}"> Remove Permanently</a>
                                    <form action="{{ route('admin.orders.destroy',$order) }}" method="post" id="delete-form-{{ $order->id }}" class="d-none">
                                            @csrf
                                            @method('delete')
                                        </form>
                                @endif
                            </div>
                        </div>
                        <div class="row">
                            @if ($order->attachments != null)
                                <div class="col-md-6 mb-5">
                                    <a class="btn btn-primary" href="{{ route('download-file', $order->id) }}">Download Attachments File</a>
                                </div>
                            @endif
                        </div>
                    </div>
              </div>
              {{--  <!-- /.card-body -->  --}}
            </div>
            {{--  <!-- /.card -->  --}}
          </div>
          {{--  <!-- /.col -->  --}}
        </div>
        {{--  <!-- /.row -->  --}}
      </div>
      {{--  <!-- /.container-fluid -->  --}}
    </section>
@endsection


@push('style-alt')
  <!-- DataTables -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.3/css/jquery.dataTables.min.css">
@endpush

@push('script-alt')
    <script
        src="https://code.jquery.com/jquery-3.6.3.min.js"
        integrity="sha256-pvPw+upLPUjgMXY0G+8O0xUf+/Im1MZjXxxgOcBQBXU="
        crossorigin="anonymous"
    >
    </script>
    <script src="https://cdn.datatables.net/1.13.3/js/jquery.dataTables.min.js"></script>
    <script>
    $("#data-table").DataTable();

    $(".delete").on("submit", function () {
        return confirm("Do you want to remove this?");
    });
    $("a.delete").on("click", function () {
        event.preventDefault();
        var orderId = $(this).attr('order-id');
        if (confirm("Do you want to remove this?")) {
            document.getElementById('delete-form-' + orderId ).submit();
        }
    });

    $(".restore").on("click", function () {
        return confirm("Do you want to restore this?");
    });

    </script>
@endpush
