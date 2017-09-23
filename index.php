<?php

include_once("RateProcessor.php");

$dataInput = file_get_contents('php://input');

//$dataInput = file_get_contents(__DIR__ . '/Samples/SampleInput2.json');

try {
    $processor = new RateProcessor($dataInput);
    $result = $processor->processData();
} catch(Exception $e) {
    $errors = array();
    $errors[] = array(
                    'msg' => $e->getMessage()
                );
    $result = array('errors' => $errors);
}

// $result will be either array('date'=>array of data) or array('errors'=>array of errors)

header('Content-Type: application/json');
exit(json_encode($result));

?>
