<?php

namespace App\Http\Controllers;

use App\Cliente;
use Stripe\Stripe;
use Stripe\Customer;
use Braintree_Gateway;

use Stripe\SetupIntent;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use Braintree_Transaction;
use Illuminate\Http\Request;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Stripe\Exception\UnexpectedValueException;
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

        $this->guardarPago($status);// guarda id transaccion en la db
		$valida=$this->validacion();// verifica el pago de braintree con el id de transaccion guardado en la db
		if($valida==true)// si es un pago valido entonces sigue el flujo
        return response()->json($status);
		return response()->json('error');
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
	
	// valida una transaccion con el id del cliente almacenado en la base de datos
	public function validacion(){
		$this->inicializa();
		$client=Cliente::find(1);
		$id=$client->transaccion."";
		// si en la transaccion aparece el id del cliente que esta en la base de datos entonces la transaccion le pertenece
		// y por tanto es valida
		$transaction = $this->pasarela->transaction()->find("{$id}");
		
		if($client->token==$transaction->customer['id'])
			return true;
		return false;
		//return response()->json($transaction);
	}
    // obtiene id de la transaccion del pago y el estado del pago
    public function guardarPago($status){
         
        
        $status=$status;
        
        $success=$status->success;// devuelve si se pago con exito , de no existir esta variable se considera que no se pudo completar el pago
        $transaccionId=$status->transaction->id;// el id de la transaccion del pago vinculado a braintree
        // aqui
        // logica para guardar en la base de datos ...
		$c=Cliente::find(1);
        $c->transaccion=$transaccionId;
        $c->save();
    }

    // stripe

    // genera token para el lado del cliente, este sirve para crear el componente que valida la tarjeta
    public function clientSecret()
    {
        // se carga la credencial stripe
        Stripe::setApiKey('sk_test_b5uMiopp4vk8X1jxsLThvQGA007gdk00tt');
        $intent = SetupIntent::create(); // crea el token para el lado del cliente
	   return response()->json($intent);
    }
	public function clientSecret3d()
    {
        // se carga la credencial stripe
        Stripe::setApiKey('sk_test_b5uMiopp4vk8X1jxsLThvQGA007gdk00tt');
		
		
		$cliente=Customer::create();
		$c=new Cliente();
        $c->token=$cliente->id;
        $c->save();
	    $intent = \Stripe\PaymentIntent::create([
			'amount' => 1200,
			'currency' => 'usd',
			'customer'=>$c->token,
			'setup_future_usage'=>'off_session'
		]);
        return response()->json($intent);
    }
    // aitoriza y salva la tarjeta
    public function autorizaStripe(Request $request)
    {
        Stripe::setApiKey('sk_test_b5uMiopp4vk8X1jxsLThvQGA007gdk00tt');
        $metodo=$request->input('metodo');
        $this->primerPagoStripe($metodo);
        return response()->json('pagado!');
    }


    public function primerPagoStripe($metodo)
    {
        // se crea un objeto usuario Stripe y se asocia la tarjeta
        $cliente=Customer::create([
            'payment_method' => $metodo,
          ]);
        // aqui lo guarlo en mi DB
        $c=new Cliente();
        $c->token=$cliente->id;
        $c->save();
        // se realiza el pago en formato entero .. ej. 20$ son 2000 , los ultimos 2 ceros son los decimales
        // 512$ son 51200
        $this->pagoStripe($cliente->id,$metodo, 1100);
        
    }
    public function recurrentePagoStripe(Request $request)
    {
        Stripe::setApiKey('sk_test_b5uMiopp4vk8X1jxsLThvQGA007gdk00tt');
        // para propositos de prueba siempre cojemos el primer cliente .. 
        $cliente=Cliente::find(1);
        // recuperamos la tarjeta del cliente
		
        $m=PaymentMethod::all([
            'customer' => $cliente->token,
            'type'=>'card'
          ]);
		//return response()->json(count($m['data']));
        $metodo=$m['data'][0]->id;
        $monto=$request->input('monto');

        // paga
        $monto=$monto.'00';
        $this->pagoStripe($cliente->token,$metodo, $monto);
        return response()->json('pagado!');
    }

    // paga
    public function pagoStripe($cliente,$metodo,$monto)
    {   // si no lanza catch es que pago ok, de lo contrario es que paso algo (sin dinero u otra cosa)
        try {
            // crea la intencion de pago en stripe y la consola del servidor lo ejecuta automaticamente
            PaymentIntent::create([
              'amount' => $monto,
              'currency' => 'usd',
              'customer' => $cliente,
              'payment_method' => $metodo,
			  
              'off_session' => true, // que no requiere intervencion del usuario
              'confirm' => true, // pues que si hombre , paga!
            ]);
            
          } catch (\Stripe\Exception\CardException $e) {
            return response()->json('se requiere autenticacion');
            echo 'Error code is:' . $e->getError()->code;
            $payment_intent_id = $e->getError()->payment_intent->id;
            $payment_intent = \Stripe\PaymentIntent::retrieve($payment_intent_id);
          }
        // logica despues del pago ...
    }
	
    public function hooks(Request $request)
    {
        Stripe::setApiKey('sk_test_b5uMiopp4vk8X1jxsLThvQGA007gdk00tt');

        $payload = json_decode($request->getContent(), true);
        $event = $payload;

        // Handle the event
        switch ($event['type']) {
            case 'payment_intent.succeeded':
                $paymentIntent = $event['data']['object']; // contains a StripePaymentIntent
                $monto=$paymentIntent['amount']; // obtiene el monto del pago pero en formato entero ej. 10$ es  1000
                $transaccion=$paymentIntent['charges']['data'][0]['id']; // obtiene el id de la transaccion
				
				if($paymentIntent['customer']!=null){
					
					$m=PaymentMethod::all([
						'customer' => $paymentIntent['customer'],
						'type'=>'card'
					  ]);
					if(count($m['data'])>0)
						return response()->json($paymentIntent);
					$payment_method = \Stripe\PaymentMethod::retrieve(
					  $paymentIntent['charges']['data'][0]['payment_method']
					);
					$payment_method->attach([
					  'customer' => $paymentIntent['customer'],
					]);	
					// $c=Cliente::find(1);
					// $c->token=$cliente->id;
					return response()->json($paymentIntent);
				}
				
				
				
                return response()->json($paymentIntent);
                break;
            case 'payment_intent.payment_failed':
                $paymentIntent = $event['data']['object']; // contains a StripePaymentMethod
                // algo fallo !!.. 
				return response()->json('se requiere autenticacion!');
                break;
            // ... handle other event types
            default:
                // Unexpected event type
                return response()->json($event);
                exit();
        }
        return response()->json($event);
    }
}
