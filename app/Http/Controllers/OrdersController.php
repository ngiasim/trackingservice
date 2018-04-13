<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Orders;
use App\Shipments;
use App\LogShipmentStatus;
use App;

class OrdersController extends Controller
{
    public function index()
    {
        $orders  =  Orders::all();
        return view('orders',compact('orders'));
    }

    public function show(Article $article)
    {
        //return $article;
    }

    public function store(Request $request)
    {
        $input = $request->all();
        $input['line_items'] = json_encode($input['line_items']);
        $input['order_id'] = $input['id'];
        unset($input['id']);

        $article = Orders::create($input);
        return response()->json('order placed');
    }

    public function accept(Request $request)
    {
        $input = $request->all();
        $tracking_number = rand(1000000,9999999);

        $order = Orders::find($input['order_id']);
        $order->tracking_number = $tracking_number;
        $order->save();

        $shopify = App::make('ShopifyAPI', [
             'API_KEY'       => 'edd5a92b1f8d47400b22964c10858047',
             'API_SECRET'    => '90dcb42ada32de7196b31a695557e161',
             'SHOP_DOMAIN'   => 'f9-dev.myshopify.com',
             'ACCESS_TOKEN'  => '35364181c599e977a5ba4d6b3401068e'
        ]);

        $call = $shopify->call(
          ['URL' => 'admin/orders/' . $order->order_id . '/fulfillments.json',
          'METHOD' => 'POST',
          'DATA' => '{
              "fulfillment": {
                "tracking_number": "'.$tracking_number.'",
                "tracking_urls": [
                  "http://localhost/boxee/public/track/'.$tracking_number.'",
                  "http://localhost/boxee/public/track/'.$tracking_number.'"
                ],
                "notify_customer": true
              }
            }']);

        $shipment = Shipments::create([
            'fk_order'        => $input['order_id'],
            'tracking_number' => $tracking_number,
            'shipment_status' => 'Shipment Created'
        ]);
        LogShipmentStatus::create([
            'fk_shipment'        => $shipment->id,
            'shipment_status_from' => '',
            'shipment_status_to' => 'Shipment Created'
        ]);
        return back()->with('success','Shipment created successfully.');
    }

    public function trackShipment($tracking_number)
    {
        $shipment = Shipments::where('tracking_number',$tracking_number)->first();
        $shipment_logs = [];
        if(count($shipment)>0){
           $shipment_logs = LogShipmentStatus::where('fk_shipment',$shipment->id)->orderBy('id','desc')->get();
        }
        return view('track-shipment',compact('tracking_number','shipment_logs'));

    }

    public function update(Request $request)
    {
        $input = $request->all();
        dd($input);
        //$article->update($request->all());

        //return response()->json($article, 200);
    }

    public function delete(Article $article)
    {
        //$article->delete();

        //return response()->json(null, 204);
    }
}
