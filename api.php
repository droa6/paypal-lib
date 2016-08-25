<?php

require __DIR__ . '/paypal_lib.php';

// get the HTTP method, path and body of the request
$method = $_SERVER['REQUEST_METHOD'];
$request = isset($_SERVER['PATH_INFO'])?explode('/', trim($_SERVER['PATH_INFO'],'/')):'';
$operation = $request!=''?array_shift($request):'';
$completeUrl = getBaseUrl()."/result";
$response = returnArray('No se ha elegido una operacion valida, consulte la documentacion.', -1);

// comentar esta linea para evitar modo directo de pruebas
$prueba="true";

if ( $operation != '' && (isset($prueba) || $method == 'POST') ) {
	switch($operation) {
		case 'subscribe':
			$plan_id = isset($prueba)?'P-9PH85681A9976030NYD7DB4I':getIf('plan_id');

			if ( $plan_id == '' || $plan_id == 'no-plan_id' ) {
				$plan_args = array (
				            'planName' => 'Hosting Basico',
				            'planDescription' => 'Plan de hosting basico $5 x mes',
				            'paymentsName' => 'Renovacion del hosting',
				            'freq' => 'Month',
				            'freqInterval' => '1',
				            'cost' => '5'
				        );

				$plan = createPlan($completeUrl, $plan_args);

				if ( isset($plan['error']) ) {
					returnJson($plan);
				}

				$plan_id = $plan['result']['id'];
			}

			$datetime = new DateTime('tomorrow');

			$agreement_args = array (
				'prueba' => isset($prueba)?'true':'false',
				'plan_id' => $plan_id,
			    'agreementName' 		=> 'Acuerdo de pago por hosting anual, cobro mensual',
			    'agreementDesc' 		=> 'Plan basico $5 x mes',
			    'agreementDate' 		=> $datetime->format('c'), // = date iso 8601
			    // Los campos a continuacion indican el NOMBRE 
			    // del campo HTML que trae el valor del formulario
			    'shippingLine1' 		=> 'lbl_shippingLine1',
				'shippingCity' 			=> 'lbl_shippingCity',
				'shippingState'	 		=> 'lbl_shippingState',
				'shippingPostal' 		=> 'lbl_shippingPostal',
				'shippingCountryCode' 	=> 'lbl_shippingCountryCode'
			);
			$response = subscribe($agreement_args);
			/*
			echo '<link href="//cdn.rawgit.com/noelboss/featherlight/1.3.5/release/featherlight.min.css" type="text/css" rel="stylesheet" />';
			echo '<body>';
			echo "$.featherlight({iframe: '".$response['result']['approvalUrl']."', iframeMaxWidth: '80%', iframeWidth: 500,
    iframeHeight: 300});";
			echo '<script src="//code.jquery.com/jquery-latest.js"></script><script src="//cdn.rawgit.com/noelboss/featherlight/1.3.5/release/featherlight.min.js" type="text/javascript" charset="utf-8"></script>';
			echo '</body>';
			return;
			*/
		break;
		case 'cancel':
			$id = getDie('id');
			$response = cancel($id);
		break;
	}
}

if ( $method == 'GET') {
	switch($operation) {
		case 'result':
			$response = complete(array_shift($request));
		break;
		case 'status':
			$response = status(array_shift($request));
		break;
	}
}

returnJson($response);

/**
 * @api {POST} ./api.php/subscribe/ Implementacion de Subscribe 
 * @apiDescription Se suscribe a un plan de pagos<br><b>Esta implementacion CREA un plan de pagos por defecto si no se provee el PLAN_ID</b>
 * @apiName subscribe
 * @apiGroup API.PHP
 *
 * @apiParam {String_Date_ISO8601} agreementDate Fecha a partir de la cual procede el acuerdo
 * @apiParam {String} shippingLine1 Direccion del cliente, linea 1
 * @apiParam {String} shippingCity Direccion del cliente, ciudad
 * @apiParam {String} shippingState Direccion del cliente, estado o provincia
 * @apiParam {String} shippingPostal Direccion del cliente, codigo postal
 * @apiParam {String} shippingCountryCode Direccion del cliente, codigo de pais, 2 caracteres
 * @apiParam {String} plan_id ID del plan de pagos a usar para la suscripcion
 *
 * @apiSuccessExample Success:
 *     Ver la libreria.
 *
 * @apiErrorExample Error:
 *     Ver la libreria.
 */

/**
 * @api {POST} ./api.php/cancel/ Implementacion de Cancel 
 * @apiDescription Cancela una suscripcion
 * @apiName cancel
 * @apiGroup API.PHP
 *
 * @apiParam {String} id ID de la suscripcion a cancelar
 *
 * @apiSuccessExample Success:
 *     Ver la libreria.
 *
 * @apiErrorExample Error:
 *     Ver la libreria.
 */

/**
 * @api {POST} ./api.php/status/ Implementacion de Status 
 * @apiDescription Obtiene los detalles de una suscripcion
 * @apiName status
 * @apiGroup API.PHP
 *
 * @apiParam {String} id ID de la suscripcion a cancelar
 *
 * @apiSuccessExample Success:
 *     Ver la libreria.
 *
 * @apiErrorExample Error:
 *     Ver la libreria.
 */

?>
