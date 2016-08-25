<?php

/*
	Common functions used across the lib
*/
    
function getBaseUrl() {
    if (PHP_SAPI == 'cli') {
        $trace=debug_backtrace();
        $relativePath = substr(dirname($trace[0]['file']), strlen(dirname(dirname(__FILE__))));
        echo "Warning: This sample may require a server to handle return URL. Cannot execute in command line. Defaulting URL to http://localhost$relativePath \n";
        return "http://localhost" . $relativePath;
    }
    $protocol = 'http';
    if ($_SERVER['SERVER_PORT'] == 443 || (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) == 'on')) {
        $protocol .= 's';
    }
    $host = $_SERVER['HTTP_HOST'];
    $request = $_SERVER['PHP_SELF'];
    return dirname($protocol . '://' . $host . $request);
}

function returnArray($m, $c, $e=null){
    $data = array (
        'result' => $m
    );
    if ( $c < 0) {
      if (isset($e) && $e!=null) {
        $data = array (
            'error' => array(
                'msg' => $m,
                'code' => $c,
                'exception' => json_decode($e->getData())
        ));
      } else { 
        $data = array (
            'error' => array(
                'msg' => $m,
                'code' => $c
        ));
      }
    }
    return $data;
}

function returnJson($data){
    if ( isset($data['error']) ) {
        http_response_code(404);
    } else {
        http_response_code(200);
    }
    if ( isset($data['result']) && isset($data['result']['approvalUrl']) ) {
        header('Location: ' . $data['result']['approvalUrl']);
    } else {
        header('Content-type: application/json');
        echo json_encode( $data );
    }
    exit(0);
}

function checkParam($v, $x) {
    if ($v == '' || $v == 'no-'.$x) {
        returnJson(returnArray('Error, no se encuentra el parametro requerido ['.$x.']', -6));
    }
}

function postKey($k, $r, $d) {
    $tmp = isset($_POST[$k])?$_POST[$k]:$d;
    if ($r) { 
        checkParam($tmp, $k);
    }
    return $tmp;
}

function getKey($k, $r, $d) {
    $tmp = isset($_GET[$k])?$_GET[$k]:$d;
    if ($r) { 
        checkParam($tmp, $k);
    }
    return $tmp;
}

function getDef($k, $d){
    return postKey($k, false, $d);
}

function getDie($k) {
    return postKey($k, true, 'no-'.$k);
}

function getIf($k) {
    return postKey($k, false, 'no-'.$k);
}

function getGetDie($k) {
    return getKey($k, true, 'no-'.$k);
}

function getGetIf($k) {
    return getKey($k, false, 'no-'.$k);
}
