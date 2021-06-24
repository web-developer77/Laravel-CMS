<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeController extends Controller
{
    public function checkout(Request $request)
    {
        try {
            \Stripe\Stripe::setApiKey(env('STRIPE_API_KEY'));

            $planPrice = $request->get('planPrice');
            $currency = $request->get('currency');

            $paymentIntent = \Stripe\PaymentIntent::create([
                'amount' => $planPrice,
                'currency' => $currency,
            ]);

            // $paymentIntent = \Stripe\PaymentIntent::create([
            //     'amount' => '100',
            //     'currency' => 'aud',
            // ]);

            $output = [
                'clientSecret' => $paymentIntent->client_secret,
            ];
            
            return createResponse(config('httpResponse.SUCCESS'), "The data has been updated successfully.", $output);
        } catch (\Exception $e) {
            \Log::error("Stripe Checkout failed".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while Stripe checkout",
                ['error' => "Error while Stripe checkout".$e->getMessage()]);
        }
    }

    public function getPrice(Request $request)
    {
        try {
            $api_key = env('STRIPE_API_KEY');
            \Stripe\Stripe::setApiKey(env('STRIPE_API_KEY'));
            $stripe = new \Stripe\StripeClient($api_key);
            $response = $stripe->prices->all(['limit' => 3]);

            $plan = $request->get('plan');
            $price_id = $plan == 1? 'price_1Ij0nIHW2tP5rCTyHzs4y0Pv' : 'price_1Ij1q4HW2tP5rCTyZaONpVMo';
            $response = $stripe->prices->retrieve(
                $price_id,
                []
            );

            $price = $response->unit_amount_decimal;
            $currency = $response->currency;

            $price = round($price/100);

            // $price = '$29';
            // $currency = 'aud';
            
            return createResponse(config('httpResponse.SUCCESS'), "The data fetched successfully.", ["price" => $price, "currency" => $currency]);
        } catch (\Exception $e) {
            \Log::error("Stripe getPrice failed".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while Stripe getPrice",
                ['error' => "Error while Stripe getPrice".$e->getMessage()]);
        }
    }


    public function getInvoice(Request $request)
    {
        try {
            $api_key = env('STRIPE_API_KEY');
            \Stripe\Stripe::setApiKey(env('STRIPE_API_KEY'));
            $stripe = new \Stripe\StripeClient($api_key);


            $invoice_list = $stripe->invoices->all();
            print_r($invoice_list);

            // $invoice_data = $stripe->invoices->retrieve(
            //     'pi_1J16zmHW2tP5rCTyYbTcGN3e',
            //     []
            // );
            // print_r($invoice_data);

            // $invoice_id = 'pi_1J16zmHW2tP5rCTyYbTcGN3e';
            // $invoice_id = $request->get('invoice_id');
            // $stripe->invoices->sendInvoice($invoice_id);
            
            return createResponse(config('httpResponse.SUCCESS'), "The invoice is sent successfully.");
        } catch (\Exception $e) {
            \Log::error("Stripe sendInvoice failed".$e->getMessage());
            return createResponse(config('httpResponse.SERVER_ERROR'),
                "Error while Stripe sendInvoice",
                ['error' => "Error while Stripe sendInvoice".$e->getMessage()]);
        }
    }
}