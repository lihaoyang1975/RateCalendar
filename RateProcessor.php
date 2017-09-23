<?php

include_once("Crc24.php");

use Bigwhoop\Crc24\Crc24;

Class RateData {
    private $srcData;

    public $periodStart;
    public $periodEnd;
    public $rate;
    public $colorCode;

    public function __construct($data) {
        $this->srcData = $data; // store the original data

        $error = '';
        $tz = new DateTimeZone('America/Denver');

        if ((!property_exists($data, 'periodStart') || empty($data->periodStart))
         && (!property_exists($data, 'periodEnd') || empty($data->periodEnd))) {
            $error .= "Missing date period. ";
        } else {
            try {
                if (!property_exists($data, 'periodStart') || empty($data->periodStart)) {
                    $this->periodStart = $this->periodEnd = new DateTime($data->periodEnd, $tz);
                } else if (!property_exists($data, 'periodEnd') || empty($data->periodEnd)) {
                    $this->periodEnd = $this->periodStart = new DateTime($data->periodStart, $tz);
                } else {
                    $this->periodStart = new DateTime($data->periodStart, $tz);
                    $this->periodEnd = new DateTime($data->periodEnd, $tz);
                    if ($this->periodStart > $this->periodEnd) {
                        list($this->periodStart, $this->periodEnd) = array($this->periodEnd, $this->periodStart);
                    }
                }
            } catch(Exception $e) {
                 $error .=  "Invalid date. ";
            }
        }

        if (!property_exists($data, 'rate') || empty($data->rate)) {
            $error .=  "Missing rate.";
        } else if (!is_numeric($data->rate)) {
            $error .=  "Invalid rate.";
        } else if ((float) $data->rate < 0) {
            $error .=  "Negative rate.";
        } else {
            $this->rate = (float) $data->rate;
        }

        if (!empty($error)) throw new Exception($error);
    }

    public function overlapsWith($data2) {
        return $data2->periodStart <= $this->periodEnd && $data2->periodEnd >= $this->periodStart;
    }

    public function mergesWith($data2) {
        $this->periodStart = min($this->periodStart, $data2->periodStart);
        $this->periodEnd = max($this->periodEnd, $data2->periodEnd);
    }

    public function format() {
        $this->periodStart = $this->periodStart->format('Y-m-d');
        $this->periodEnd = $this->periodEnd->format('Y-m-d');
        $this->rate = number_format($this->rate, 2);
        $this->colorCode = $this->generateColorCode();
    }

    /**
    * We are not caching the generated color codes, because the CRC24 hashing algorithm is super fast.
    * If we were handling a large data set, and noticed any performance hit, we would then have the option of
    * using an associative array to store the rate -> color code pairs.
    */
    private function generateColorCode() {
        return dechex(Crc24::hash($this->rate));
    }

    public function getSrcData() {
        return $this->srcData;
    }
}

class RateProcessor {

    private $dataInput;
    private $dataOutput = array();

    public function __construct($dataInput) {
        if (is_array($dataInput)) {
            $this->dataInput = $dataInput;
        } else {
            $this->dataInput = json_decode($dataInput);
            if (is_null($this->dataInput)) {
                throw new Exception("Invalid JSON string.");
            }
        }
    }

    public function processData() {
        // Import the source data, converting the data into proper types
        $errors = $this->importData();
        if (!empty($errors)) {
            return array('errors' => $errors);
        }

        // Put the imported data into chronological order.
        // If this step is skipped, data will still be processed, but the output will not be in chronological order.
        $this->sortData();

        // Find overlapping date periods with the same rate and merge them.
        $errors = $this->mergeData();
        if (!empty($errors)) {
            return array('errors' => $errors);
        }

        foreach($this->dataOutput as &$data) {
            $data->format();
        }

        return array('data' => $this->dataOutput);
    }

    private function importData() {
        $errors = array();

        foreach($this->dataInput as $data) {
            try {
                $this->dataOutput[] = new RateData($data);
            } catch (Exception $e) {
                $errors[] = array(
                                'msg' => $e->getMessage(),
                                'data'  => json_encode($data)
                            );
            }
        }

        return $errors;
    }

    private function sortData() {
        usort($this->dataOutput, "self::compareData");
    }

    private static function compareData($data1, $data2) {
        $date1 = $data1->periodStart;
        $date2 = $data2->periodStart;
        return $date1 == $date2 ?  0 : ($date1 < $date2 ? -1 : 1);
    }

    /**
    * Find all the overlapping periods with the same rate and combine them.
    * If two periods overlap but have different rates, report it as an error.
    */
    private function mergeData() {
        $errors = array();
        // We start with the first one and check whether it overlaps with the others.
        // If we find overlaps, we'll merge them all into the first one and then remove them and re-index the array.
        // If we don't find overlaps, we'll move onto the next one and repeat the process until we are done.
        $i = 0;
        do {
            $dataToCheck = &$this->dataOutput[$i];
            $count = count($this->dataOutput);
            $foundOverlapWithSameRate = false;

            for($j=$i + 1; $j < $count; $j++) {
                $dataCheckedAgainst = $this->dataOutput[$j];

                if ($dataToCheck->overlapsWith($dataCheckedAgainst)) {
                    if ($dataToCheck->rate == $dataCheckedAgainst->rate) {
                        $foundOverlapWithSameRate = true;
                        $dataToCheck->mergesWith($dataCheckedAgainst);
                        unset($this->dataOutput[$j]);
                    } else {
                        $errors[] = array(
                                        'msg' => 'Data found with overlapping dates but different rates.',
                                        'data'  => json_encode($dataToCheck->getSrcData()) . ',' .json_encode($dataCheckedAgainst->getSrcData())
                                    );
                    }
                }
            }

            if (!$foundOverlapWithSameRate) { // Done with this one, move onto the next one
                $i++;
            } else { // Mergers happened, so we need re-index the array.
                $this->dataOutput = array_values($this->dataOutput);
            }
        } while ($i < count($this->dataOutput));

        return $errors;
    }
}

?>
