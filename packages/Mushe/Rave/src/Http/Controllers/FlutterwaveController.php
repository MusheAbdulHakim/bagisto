<?php
namespace Mushe\Rave\Http\Controllers;

use Illuminate\Http\Request;
use Webkul\Checkout\Facades\Cart;
use Webkul\Sales\Repositories\OrderRepository;
use Webkul\Sales\Repositories\InvoiceRepository;



class FlutterwaveController extends Controller {


    /**
     * OrderRepository $orderRepository
     *
     * @var \Webkul\Sales\Repositories\OrderRepository
     */
    protected $orderRepository;
    /**
     * InvoiceRepository $invoiceRepository
     *
     * @var \Webkul\Sales\Repositories\InvoiceRepository
     */
    protected $invoiceRepository;

    /**
     * Create a new controller instance.
     *
     * @param  \Webkul\Attribute\Repositories\OrderRepository  $orderRepository
     * @return void
     */
    public function __construct(OrderRepository $orderRepository,  InvoiceRepository $invoiceRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->invoiceRepository = $invoiceRepository;
    }

    public function index(Request $request){
        $channel = core()->getCurrentChannel();
        $cart = Cart::getCart();
        $billingAddress = $cart->billing_address;

        $shipping_rate = $cart->selected_shipping_rate ? $cart->selected_shipping_rate->price : 0; // shipping rate
        $discount_amount = $cart->discount_amount; // discount amount
        $total_amount =  ($cart->sub_total + $cart->tax_total + $shipping_rate) - $discount_amount; // total amount
        $details = array(
            "public_key" => core()->getConfigData('sales.paymentmethods.rave.public_key'),
            "currency" => $channel->base_currency->code ?? 'GHS',
            "amount" => $total_amount,
            "fullname" => $billingAddress->name,
            "email" => $billingAddress->email,
            "tx_ref" => $cart->id,
            "redirect_url" => route('rave.verify'),
            "customer" => [
                "email" => $billingAddress->email,
                "phone_number" => $billingAddress->phone,
                "name" => $billingAddress->name,
            ],
            "customizations" => [
                "title" => env('APP_NAME'),
                "description" => "Payment for an awesome cruise",
                "logo" => core()->getCurrentChannel()->logo_url ?? asset('themes/velocity/assets/images/logo-text.png')
            ],              
        );
        $data = json_encode($details);
        $request->session()->put('public_key', core()->getConfigData('sales.paymentmethods.rave.public_key'));
        $request->session()->put('total_amount', $total_amount);
        $request->session()->put('reference', $cart->id);
        $request->session()->put('currency', $channel->base_currency->code ?? 'GHS');

        return view('rave::flutterwave',compact(
            'data',
        ));
    }

    public function verify(Request $request){
        $status = $request->input('status');
        $ref = $request->input('tx_ref');
        if (!empty($ref)) {

            $url = 'https://api.flutterwave.com/v3/transactions/'.$request->input('transaction_id').'/verify';   
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "GET",
                CURLOPT_HTTPHEADER => array(
                "Authorization: Bearer ".core()->getConfigData('sales.paymentmethods.rave.secret_key'),
                "Cache-Control: no-cache",
                ),
            ));
            $response = json_decode(curl_exec($curl), true);
            $total_amount = sprintf("%0.2f",session('total_amount'));
            $amount_paid = sprintf("%0.2f",$response['data']['amount']);
            if (($status === "successful") && ($amount_paid === $total_amount) && ($response['data']['currency'] === session("currency"))) {
                $order = $this->orderRepository->create(Cart::prepareDataForOrder());
                $this->orderRepository->update(['status' => 'processing'], $order->id);
                if ($order->canInvoice()) {
                    $this->invoiceRepository->create($this->prepareInvoiceData($order));
                }
                Cart::deActivateCart();
                session()->flash('order', $order);
                return redirect()->route('shop.checkout.success');
            }else{
                session()->flash('error', 'Payment is either cancelled or the transaction failed.');
                return redirect()->route('shop.checkout.cart.index');
            }
            
        }else {
            session()->flash('error', 'No reference supplied.');
            return redirect()->route('shop.checkout.cart.index');
        }
    }

   
    /**
     * Prepares order's invoice data for creation.
     *
     * @return array
     */
    protected function prepareInvoiceData($order)
    {
        $invoiceData = ["order_id" => $order->id,];

        foreach ($order->items as $item) {
            $invoiceData['invoice']['items'][$item->id] = $item->qty_to_invoice;
        }

        return $invoiceData;
    }
    
}