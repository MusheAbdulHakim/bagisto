@extends('shop::layouts.master')

@section('page_title')
    {{ __('Flutterwave Checkout') }}
@stop

@section('content-wrapper')

@endsection

@push('scripts')
<script src="https://checkout.flutterwave.com/v3.js"></script>
<script>
    var options = JSON.parse('{!! ($data) !!}');
    options.onclose = function(incomplete) {
        if (incomplete || window.verified === false) {
            window.location.href="checkout/cart";
        }
    },
    
    FlutterwaveCheckout(options);
  </script>
@endpush