<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <!-- Load PayPal's checkout.js Library. -->
<script src="https://www.paypalobjects.com/api/checkout.js" data-version-4 log-level="warn"></script>

<!-- Load the client component. -->
<script src="https://js.braintreegateway.com/web/3.57.0/js/client.min.js"></script>

<!-- Load the PayPal Checkout component. -->
<script src="https://js.braintreegateway.com/web/3.57.0/js/paypal-checkout.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
{{-- stripe integracion --}}
<script src="https://js.stripe.com/v3/"></script>

      </head>
      <body>
        {{ $cliente ?? $cliente, '' }}
        <br>
        <br>
        <input id="valor" type="number" name='valor' placeholder="Valor" />
        <button id="enviar">Simular pago recurrente</button>
        <br>
        <br>
        <div id="paypal-button"></div>
        <br>
        <br>
        <div id="card-element"></div>
        <button id="card-button" disabled>Stripe Inicial</button>
		<br>
		<br>
        <button id="card-button3d">Stripe Inicial 3D Secure</button>

        <br>
        <br>
        <br>
        <br>
        <input type="number" id="valorStripe" placeholder="valor para Stripe"/>
        <br>
        <button id="manual">pago manual (cojido del input)</button>
        
        <script>
          
          $('#enviar').click(function(){
            $.post('{{ route('api.otroPago') }}',{valor:$('#valor').val()}, function (response) {
              if (response.success) {
                alert('completado!');
              } else {
                alert('Failed!');
              }
            }, 'json');
          });
          $('#manual').click(function(){
            $.post('{{ route('api.recurrentePagoStripe') }}',{monto:$('#valorStripe').val()}, function (response) {
              
                alert(response);
              
            }, 'json');
          });
          // Create a client.
var client=braintree.client.create({
  
  authorization: '{{$cliente ?? ''}}'
}, function (clientErr, clientInstance) {

  // Stop if there was a problem creating the client.
  // This could happen if there is a network error or if the authorization
  // is invalid.
  if (clientErr) {
    console.error('Error creating client:', clientErr);
    return;
  }

  // Create a PayPal Checkout component.
  braintree.paypalCheckout.create({
    client: clientInstance
  }, function (paypalCheckoutErr, paypalCheckoutInstance) {

    // Stop if there was a problem creating PayPal Checkout.
    // This could happen if there was a network error or if it's incorrectly
    // configured.
    if (paypalCheckoutErr) {
      console.error('Error creating PayPal Checkout:', paypalCheckoutErr);
      return;
    }

    // Set up PayPal with the checkout.js library
    paypal.Button.render({
      env: 'sandbox', // or 'sandbox'

      payment: function () {
        return paypalCheckoutInstance.createPayment({
          flow: 'vault',
          billingAgreementDescription: 'Your agreement description',
          enableShippingAddress: true,
          shippingAddressEditable: false,
          shippingAddressOverride: {
            recipientName: 'Scruff McGruff',
            line1: '1234 Main St.',
            line2: 'Unit 1',
            city: 'Chicago',
            countryCode: 'US',
            postalCode: '60652',
            state: 'IL',
            phone: '123.456.7890'
          }
        });
      },

      onAuthorize: function (data, actions) {
        //console.log('checkout.js done', JSON.stringify(data, 0, 2));
        return paypalCheckoutInstance.tokenizePayment(data, function (err, payload) {
          // Submit `payload.nonce` to your server.
          console.log('checkout.js done', JSON.stringify(payload, 0, 2));
          $.post('{{ route('api.autoriza') }}', {payload}, function (response) {
            if (response.success) {
              alert('Autorizado!');
            } else {
              alert('Failed!');
            }
          }, 'json');
          //-end onAuthorize
        });
      },

      onCancel: function (data) {
        console.log('checkout.js payment cancelled', JSON.stringify(data, 0, 2));
      },

      onError: function (err) {
        console.error('checkout.js error', err);
      }
    }, '#paypal-button').then(function () {
      // The PayPal button will be rendered in an html element with the id
      // `paypal-button`. This function will be called when the PayPal button
      // is set up and ready to be used.
    });

  });

});

// stripe integracion
var stripe = Stripe('pk_test_MxsSkm9CMGyKB8QNHMWlcYTQ002YWZ09KF');

var elements = stripe.elements();
var cardElement = elements.create('card',{
  hidePostalCode: true,
});
cardElement.mount('#card-element');
//var cardholderName = document.getElementById('cardholder-name');
var cardButton = document.getElementById('card-button');
var cardButton3d = document.getElementById('card-button3d');
var clientSecret3d = cardButton.dataset.secret;
var clientSecret = cardButton.dataset.secret;
var respuesta=null;
fetch('/api/clientSecret').then(function (r) {
		return r.json();
	}).then(function (response) {
		respuesta = response;
    clientSecret=respuesta.client_secret;
		console.log("Fetched PI: ", response);
		
        });
fetch('/api/clientSecret3d').then(function (r) {
		return r.json();
	}).then(function (response) {
		respuesta = response;
    clientSecret3d=respuesta.client_secret;
		console.log("Fetched PI: ", response);
		
        });

cardButton.addEventListener('click', function(ev) {

  stripe.confirmCardSetup(
    clientSecret,
    {
      payment_method: {
        card: cardElement,
        billing_details: {
          name: 'nombre de prueba',
        },
      },
    }
  ).then(function(result) {
    console.log(result);
    if (result.error) {
      // Display error.message in your UI.
      alert('algo paso!');

    } else {
      // The setup has succeeded. Display a success message.
      console.log(result["setupIntent"]["payment_method"]);
      $.post('{{ route('api.autorizaStripe') }}',{metodo:result["setupIntent"]["payment_method"]}, function (response) {
              console.log(response);
            }, 'json');

      alert('todo bien!!');

    }
  });
});

cardButton3d.addEventListener('click', function(ev) {

  stripe.confirmCardPayment(clientSecret3d, {
  payment_method: {
    card: cardElement,
    billing_details: {
      name: 'nombre apellido'
    }
  },
  setup_future_usage: 'off_session'
}).then(function(result) {
    console.log(result);
    if (result.error) {
      // Display error.message in your UI.
      alert('algo paso!');

    } else {
      // The setup has succeeded. Display a success message.
      console.log(result['paymentIntent']["payment_method"]);
      // $.post('{{ route('api.guardarToken') }}', function (response) {
              // console.log(response);
            // }, 'json');
	
      alert('todo bien!!');
	  window.location.reload() // recarga la web cuando se paga para volver a generar los clientsecret desde el servidor

    }
  });
});
        </script>
      </body>
</html>
