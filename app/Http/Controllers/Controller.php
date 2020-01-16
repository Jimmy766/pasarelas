<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Braintree_Gateway;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    public function inicio(){
        $pasarela=new Braintree_Gateway([
            'environment' => 'sandbox',
            'merchantId' => '5rhrmyjvb8grrg3p',
            'publicKey' => 'fxckz62h6j8gfmq3',
            'privateKey' => '35700099a2f8e6b64d3386ef86609b70'
        ]);
        $clientToken = $pasarela->clientToken()->generate();
        echo $clientToken;
        return view('welcome',['cliente'=>$clientToken]);
    }
}
