<?php

namespace App\Http\Controllers;

use App\Cliente;
use Braintree_Gateway;
use Braintree_Transaction;
use Illuminate\Http\Request;

use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;


class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;
    private $pasarela ; // pasarela braintree
    private $cliente; // id cliente de braintree
    // inicializa sdk braintree
    public function inicializa(){
        $this->pasarela=new Braintree_Gateway([
            'environment' => 'sandbox',
            'merchantId' => '5rhrmyjvb8grrg3p',
            'publicKey' => 'fxckz62h6j8gfmq3',
            'privateKey' => '35700099a2f8e6b64d3386ef86609b70'
        ]);
    }
    // pagina de inicio
    public function inicio(){
        
        $this->inicializa();
        $clientToken = $this->pasarela->clientToken()->generate(); // genera un token para el lado del cliente
        
        return view('welcome',['cliente'=>$clientToken]);
    }
    // autoriza y realiza el primer pago
    public function autoriza(Request $request)
    {
        $this->inicializa();
        // obtiene el token del metodo de pago
        $payload = $request->input('payload', false);
        $nonce = $payload['nonce'];

        $this->creaCliente();
        
        $status=$this->primerPago($nonce);
        
        $this->guardarPago($status);
        
        return response()->json($status);
    } 
    public function simulaPagoRecurrente(Request $request)
    {
        $valor = $request->input('valor', '10');
        $this->inicializa();
        $this->recuperaToken();// recupera el token del cliente de la DB
        $status=$this->pagoRecurrente($valor);

        $this->guardarPago($status);
        return response()->json($status);
    } 
    // hace el pago sin intervencion del cliente
    public function pagoRecurrente($valor)
    {
        
        
        $status = $this->pasarela->transaction()->sale([
            'amount' => $valor,
            'customerId' => $this->cliente,
            'transactionSource' => "recurring", 
            'options' => [
                'submitForSettlement' => True  
            ]
        ]);
        return $status;
    }

    // se hace el pago y se asocia paypal con el cliente de braintree
    public function primerPago($nonce)
    {
        
        $status = $this->pasarela->transaction()->sale([
            'amount' => '15.00',
            'paymentMethodNonce' => $nonce,
            'customerId' => $this->cliente,
            'options' => [
                'submitForSettlement' => true,
                'storeInVaultOnSuccess' => true,
            ]
        ]);
        return $status;
    }
    // crea el cliente en braintree
    public function creaCliente()
    {
        $id=null; // id del cliente, esto es lo que se debe guardar en la DB
        $this->cliente = $this->pasarela->customer()->create([
            'firstName' => 'Mike',
            'lastName' => 'Jones',
            'company' => 'Jones Co.',
            'email' => 'mike.jones@example.com',
            'phone' => '281.330.8004',
            'fax' => '419.555.1235',
            'website' => 'http://example.com'
        ]);
        $this->cliente=$this->cliente->customer->id; // asi es como se obtiene el id del cliente
        // se guarda en la DB
        $c=new Cliente();
        $c->token=$this->cliente;
        $c->save();
    }
    public function recuperaToken()
    {
        // aqui se asume que el cliente en conexto esta asociado con su respectivo token
        // por eso para propositos de prueba solo se usara el primer cliente
        $this->cliente=Cliente::find(1)->token;
    }
    // obtiene id de la transaccion del pago y el estado del pago
    public function guardarPago($status){
         
        
        $status=$status;
        
        $success=$status->success;// devuelve si se pago con exito , de no existir esta variable se considera que no se pudo completar el pago
        $transaccionId=$status->transaction->id;// el id de la transaccion del pago vinculado a braintree
        // aqui
        // logica para guardar en la base de datos ...
    }
}
