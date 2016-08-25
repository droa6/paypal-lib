<?php
// Version final
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/common.php';

use PayPal\Api\ChargeModel;
use PayPal\Api\Currency;
use PayPal\Api\MerchantPreferences;
use PayPal\Api\PaymentDefinition;
use PayPal\Api\Plan;
use PayPal\Api\Patch;
use PayPal\Api\PatchRequest;
use PayPal\Api\Agreement;
use PayPal\Api\Payer;
use PayPal\Api\ShippingAddress;
use PayPal\Api\AgreementStateDescriptor;
use PayPal\Common\PayPalModel;

use PayPal\Api\PayerInfo;
use PayPal\Api\CreditCard;
use PayPal\Api\FundingInstrument;

/**
 * @api {Definicion} createPlan($returnUrl,$arg_plan) Crear plan 
 * @apiDescription Crea un plan de pagos para una subscripcion en Paypal
 * @apiName createPlan
 * @apiGroup Paypal_Lib
 *
 * @apiParam {String} returnUrl URL a la cual retornara cuando el usuario termina de autorizar el pago.
 * @apiParam {Array} arg_plan Arreglo de parametros<br>planType - INFINITO por defecto, puede ser REGULAR<br>currency - USD por defecto, permite cualquier codigo de moneda admitido por Paypal<br>freqCycles - 0 por defecto, cantidad de veces que se va a realizar el cobro<br>planName - Requerido, no nombre del plan<br>planDescription - Requerido, descripcion del plan<br>paymentsName - Requerido, nombre del pago cuando se cobra<br>freqInterval - Requerido, cada cuantos [freq] cobrar<br>cost - Requerido, cuanto se cobra por cada pago<br>freq - Month por defecto, tipo de frecuencia del cobro
 *
 * @apiSuccessExample Success:
 *     array( 
 *          'msg' => 'Plan de pagos creado y activo.',
 *          'id' => [id del plan recien creado]
 *     );
 *
 * @apiErrorExample Error:
 *     'Error activando el plan de pagos.'
 *     'Error creando el plan de pagos.'
 */
function createPlan($returnUrl, $arg_plan) {
    global $apiContext;

    $plan            = new Plan();

    $planType        = isset($arg_plan['planType'])?$arg_plan['planType']:"INFINITE";
    $currency        = isset($arg_plan['currency'])?$arg_plan['currency']:"USD";
    $freqCycles      = isset($arg_plan['freqCycles'])?$arg_plan['freqCycles']:"0";

    $planName        = $arg_plan['planName']; 
    $planDescription = $arg_plan['planDescription']; 
    $paymentsName    = $arg_plan['paymentsName'];  
    $freqInterval    = $arg_plan['freqInterval'];  
    $cost            = $arg_plan['cost'];
    $freq            = isset($arg_plan['freq'])?$arg_plan['freq']:"Month";         

    $plan->setName($planName)
         ->setDescription($planDescription)
         ->setType($planType);

    $paymentDefinition = new PaymentDefinition();

    $paymentDefinition->setName($paymentsName)
                      ->setType("REGULAR")
                      ->setFrequency($freq)
                      ->setFrequencyInterval($freqInterval)
                      ->setCycles($freqCycles)
                      ->setAmount(new Currency(array(
                                                    'value' => $cost,
                                                    'currency' => $currency
                                                    )
                                              )
                      );

    $trialDefinition = new PaymentDefinition();
    $trialDefinition->setName("Trial")
                      ->setType("TRIAL")
                      ->setFrequency($freq)
                      ->setFrequencyInterval("1")
                      ->setCycles("1")
                      ->setAmount(new Currency(array(
                                                    'value' => 0,
                                                    'currency' => $currency
                                                    )
                                              )
                      );
    
    $merchantPreferences = new MerchantPreferences();
    
    $merchantPreferences->setReturnUrl("$returnUrl/true")
                        ->setCancelUrl("$returnUrl/false")
                        ->setAutoBillAmount("yes")
                        ->setInitialFailAmountAction("CONTINUE")
                        ->setMaxFailAttempts("0");
    
    $plan->setPaymentDefinitions(array(
        $trialDefinition, 
        $paymentDefinition 
    ));
    
    $plan->setMerchantPreferences($merchantPreferences);
    
    try {
        $createdPlan = $plan->create($apiContext);
    }
    catch (Exception $ex) {
        return returnArray('Error creando el plan de pagos.', -4, $ex);
    }

    // # Update a plan ###
    // ### Making Plan Active
    try {
        $patch = new Patch();
        $value = new PayPalModel('{
                                    "state":"ACTIVE"
                                }');
        $patch->setOp('replace')->setPath('/')->setValue($value);
        $patchRequest = new PatchRequest();
        $patchRequest->addPatch($patch);
        $createdPlan->update($patchRequest, $apiContext);
        $plan = Plan::get($createdPlan->getId(), $apiContext);
    }
    catch (Exception $ex) {
        return returnArray('Error activando el plan de pagos.', -5, $ex);
    }

    $data = array( 'msg' => 'Plan de pagos creado y activo.',
                   'id' => $plan->getId() );

    return returnArray($data, 0);
}

function subscribe($agreement_args) {
    return subscribePaypal($agreement_args);
}

/**
 * @api {Definicion} subscribe($agreement_args) Suscribir 
 * @apiDescription Genera un acuerdo de subscripcion a un plan de pago en Paypal<br>Tipo de pago: Paypal<br>No permite pago usando tarjeta de credito.
 * @apiName suscribe
 * @apiGroup Paypal_Lib
 *
 * @apiParam {Array} agreement_args Arreglo de parametros<br>plan_id - INFINITO por defecto, puede ser REGULAR<br>agreementName - USD por defecto, permite cualquier codigo de moneda admitido por Paypal<br>agreementDesc - 0 por defecto, cantidad de veces que se va a realizar el cobro<br>agreementDate - Requerido, no nombre del plan<br>shippingLine1 - Requerido, descripcion del plan<br>shippingCity - Requerido, nombre del pago cuando se cobra<br>shippingState - Requerido, cada cuantos [freq] cobrar<br>shippingPostal - Requerido, cuanto se cobra por cada pago<br>shippingCountryCode - Month por defecto, tipo de frecuencia del cobro
 *
 * @apiSuccessExample Success:
 *     array( 
 *          'msg' => 'Acuerdo de pago creado y listo para aprobar.',
 *          'plan' => [id del plan usado para esta subscripcion]
 *          'approvalUrl' => [URL en la que el usuario ingresa su info de pago y autoriza o rechaza el cobro] );
 *     );
 *
 * @apiErrorExample Error:
 *     'Error creando el acuerdo de subscripcion.'
 */
function subscribePaypal($agreement_args) {
    global $apiContext;
    $baseUrl = getBaseUrl();

    $plan_id = $agreement_args['plan_id'];

    $agreementDate = $agreement_args['agreementDate'];

    if ( isset($agreement_args['prueba']) && $agreement_args['prueba'] == 'true') {
        $agreementName = "nombre del acuerdo";
        $agreementDesc = "descripcion del acuerdo";
        $shippingLine1   = "1030 Crown Pointe Parkway";
        $shippingCity    = "Atlanta";
        $shippingState   = "GA";
        $shippingPostal  = "30338";
        $shippingCountryCode = "US";
    } else {
        $agreementName = $agreement_args['agreementName'];
        $agreementDesc = $agreement_args['agreementDesc'];

        $shippingLine1   = getDie($agreement_args['shippingLine1']);
        $shippingCity    = getDie($agreement_args['shippingCity']);
        $shippingState   = getDie($agreement_args['shippingState']);
        $shippingPostal  = getDie($agreement_args['shippingPostal']);
        $shippingCountryCode = getDie($agreement_args['shippingCountryCode']);
    }

    $agreement   = new Agreement();

    $agreement->setName($agreementName)
              ->setDescription($agreementDesc)
              ->setStartDate($agreementDate); 

    // Add Plan ID
    $plan = new Plan();
    $plan->setId($plan_id);
    $agreement->setPlan($plan);
    // Add Payer
    $payer = new Payer();
    $payer->setPaymentMethod('paypal');
    $agreement->setPayer($payer);
    // Add Shipping Address
    $shippingAddress = new ShippingAddress();

    $shippingAddress->setLine1($shippingLine1)
                    ->setCity($shippingCity)
                    ->setState($shippingState)
                    ->setPostalCode($shippingPostal)
                    ->setCountryCode($shippingCountryCode);

    $agreement->setShippingAddress($shippingAddress);
    
    try {
        $agreement   = $agreement->create($apiContext);
        $approvalUrl = $agreement->getApprovalLink();
    }
    catch (Exception $ex) {
        return returnArray('Error creando el acuerdo de subscripcion.', -10,$ex);
    }

    $data = array( 'msg' => 'Acuerdo de pago creado y listo para aprobar.',
                   'plan' => $plan->getId(),
                   'approvalUrl' => $approvalUrl );

    return returnArray($data, 0);
}

/**
 * @api {Definicion} status($id) Estado 
 * @apiDescription Consulta el estado de un contrato de pago en Paypal
 * @apiName status
 * @apiGroup Paypal_Lib
 *
 * @apiParam {String} id ID del acuerdo de pago a consultar.
 *
 * @apiSuccessExample Success:
 *     array( 'msg' => 'Informacion del acuerdo de pago.',
 *             'state' => [estado - Active|Cancelled],
 *             'next_billing_date' => [proxima fecha de pago (si existe)],
 *             'last_payment_amount' => ultimo pago (si existe),
 *             'last_payment_date' => ultima fecha de pago (si existe),
 *             'agreement_data' => acuerdo de pago completo en JSON );
 *
 * @apiErrorExample Error:
 *     'Error obteniendo la informacion de este arreglo de pago.'
 */
function status($id) {
    global $apiContext;
    // Make a get call to retrieve the executed agreement details
    try {
        $agreement = Agreement::get($id, $apiContext);
    }
    catch (Exception $ex) {
        return returnArray('Error obteniendo la informacion de este arreglo de pago.',-9,$ex);
    }

    $now = DateTime::createFromFormat('Y-m-d',date('Y-m-d'));    
    $start_date = DateTime::createFromFormat('Y-m-d', substr($agreement->start_date,0,10));
    
    $data = array( 'msg' => 'Informacion del acuerdo de pago.',
                   'state' => $agreement->state,
                   'start_date' => $start_date->format('Y-m-d'),
                   //'now' => $now->format('Y-m-d'),                   
                   'next_billing_date' => isset($agreement->agreement_details->next_billing_date)?$agreement->agreement_details->next_billing_date:'not defined',
                   /*
                   'last_payment_amount' => isset($agreement->agreement_details->last_payment_amount)?$agreement->agreement_details->last_payment_amount->value:'not defined',
                   'last_payment_date' => isset($agreement->agreement_details->last_payment_date)?$agreement->agreement_details->last_payment_date:'not defined',
                   */
                   'agreement_data' => json_decode($agreement->toJSON()));

    return returnArray($data, 0);
}

/**
 * @api {Definicion} cancel($id,$note) Cancelar 
 * @apiDescription Cancela una subscripcion de pago activa en Paypal
 * @apiName cancel
 * @apiGroup Paypal_Lib
 *
 * @apiParam {String} id ID del acuerdo de pago a cancelar
 * @apiParam {String} note Opcional, nota a incluir en el historial del acuerdo de pago en Paypal, por defecto tiene un valor ('Cancelando el contrato')
 *
 * @apiSuccessExample Success:
 *     array('msg' => 'Acuerdo de pago cancelado.',
 *           'agreement_data' => Acuerdo de pago en JSON)
 *                     
 *
 * @apiErrorExample Error:
 *     'Error obteniendo el arreglo de pago para cancelar.'
 *     'Error, el acuerdo de pago ya se encuentra cancelado.'
 *     'Error cancelando el acuerdo de pago'.
 */
function cancel($id, $note="Cancelando el contrato") {
    global $apiContext;

    try {
        $createdAgreement = Agreement::get($id, $apiContext);
    }
    catch (Exception $ex) {
        return returnArray('Error obteniendo el arreglo de pago para cancelar.', -6,$ex);
    }
    
    if ( $createdAgreement->state == "Cancelled" ) { 
        return returnArray(array('msg' => 'Error, el acuerdo de pago ya se encuentra cancelado.',
                              'agreement_data' => json_decode($createdAgreement->toJSON())) ,
                           -11);
    }

    $agreementStateDescriptor = new AgreementStateDescriptor();
    $agreementStateDescriptor->setNote($note);

    try {

        $createdAgreement->cancel($agreementStateDescriptor, $apiContext);

        // Lets get the updated Agreement Object
        $agreement = Agreement::get($id, $apiContext);

    } catch (Exception $ex) {
        return returnArray('Error cancelando el acuerdo de pago.', -7,$ex);
    }

    return returnArray( array('msg' => 'Acuerdo de pago cancelado.',
                              'agreement_data' => json_decode($agreement->toJSON())) ,
                        0);
}

/**
 * @api {Definicion} complete($result) Completar el pago 
 * @apiDescription Activa una subscripcion de pago cuando el usuario lo autoriza.
 * @apiName complete
 * @apiGroup Paypal_Lib
 *
 * @apiParam {Boolean} result 'true'/'false' dependiendo si el cliente autoriza el pago o no.
 * @apiParam {String} token Incluido por defecto, lo incluye el SDK de Paypal cuando llama de vuelta con la autorizacion del pago.
 *
 * @apiSuccessExample Success:
 *     array('msg' => 'Acuerdo de pago aceptado por el cliente.')
 *
 * @apiErrorExample Error:
 *     'Error intentando ejecutar el arreglo de pago.'
 *     'Acuerdo de pago no aprobado por el cliente.' - Si el cliente cancelo el proceso de pago desde Paypal, retorna este valor.
 */
function complete($result) {
    global $apiContext;
    // ## Approval Status
    // Determine if the user accepted or denied the request
    if ( $result == 'true') {
        // #Execute Agreement ##########################################################################################
        // This is the second part of CreateAgreement Sample.
        // Use this call to execute an agreement after the buyer approves it
        $token     = getGetDie('token');
        $agreement = new Agreement();
        try {
            // ## Execute Agreement
            // Execute the agreement by passing in the token
            $agreement->execute($token, $apiContext);
        }
        catch (Exception $ex) {
            return returnArray('Error intentando ejecutar el arreglo de pago.', -2,$ex);
        }
        return returnArray( 
                array('msg' => 'Acuerdo de pago aceptado por el cliente.',
                      'agreement_data' => json_decode($agreement->toJSON())
                ) , 0);
    }
    return returnArray('Acuerdo de pago no aprobado por el cliente.',-8);
}

?>