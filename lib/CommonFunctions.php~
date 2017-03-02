<?php
/**
 * a frequently used php functions library.
 * @stone
 */

/**
 * a function to generate a random string.
 * @param $length the length of the random string.
 * @param $flag
 * 1-get only lower-case letters string,
 * 2-get only capital letters string,
 * 3-get only letter string, 
 * 4-get numeric string,
 * 5-get a string is made up of lower-case letters and numbers,
 * 6-get a string is made up of capital letters and numbers,
 * otherwise you will get a string is made up of case letters and numbers.
 * @return a string respresent the random string.
 * @author stone
 */
function get_random_string($length, $flag = 0){
    $candidateCharacters = '';
    $lowerCaseLetters = 'abcdefghijklmnopqrstuvwxyz'; // lower-case letters
    $capitalLetters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; // capital letters
    $numbers = '0123456789'; // numbers
    switch ($flag) {
        case 1:
            $candidateCharacters = $lowerCaseLetters;
            break;
        case 2:
            $candidateCharacters = $capitalLetters;
            break;
        case 3:
            $candidateCharacters = $lowerCaseLetters.$capitalLetters;
            break;
        case 4:
            $candidateCharacters = $numbers;
            break;
        case 5:
            $candidateCharacters = $lowerCaseLetters.$numbers;
            break;
        case 6:
            $candidateCharacters = $capitalLetters.$numbers;
            break;
        default:
            $candidateCharacters = $lowerCaseLetters.$capitalLetters.$numbers;
            break;
    }

    $resultString = '';
    $randomLength = strlen($candidateCharacters) - 1;
    for ($i = 0; $i < $length; $i++) {
        $resultString .= $candidateCharacters[rand(0, $randomLength)];
    }
    return $resultString;
}

/**
 * generate paramaters url string from array
 * @param $params array paramaters to generate url string.
 * @param $keepTail 0-not keep the last & in string, 1-keep the last & in string.
 * @param $filterEmpty 0-not filter empty value, 1-filter empty value.
 * @param $filterArray 0-not filter array value, 1-filter array value.
 * @param $notArray an array include the paramater name which can not be from the string.
 * @author stone
 */
function generate_url_params($params, $keepTail = 0, $filterEmpty = 0, $filterArray = 0, $notArray = []){
    if (!empty($params) && is_array($params)) {
        $result = '';
        foreach ($params as $key => $value) {
            if (($filterEmpty && $value === '') || ($filterArray && is_array($value)) || (!empty($notArray) && is_array($notArray) && in_array($key, $notArray))) {
                continue;
            }
            $result .= $key.'='.$value.'&';
        }
        if ($keepTail) {
            return $result;
        } else {
            return rtrim($result, '&');
        }
    } else {
        return ['code' => -1, 'message' => '$params must been an array with elements'];
    }
}

/**
 * generate xml from array
 * @param $params array
 * @author stone
 */
function array_to_xml($params){
    if (!empty($params) && is_array($params)) {
        $xml = '<xml>';
        foreach ($params as $key => $value) {
            if (is_numeric($value)) {
                $xml .= '<'.$key.'>'.$value.'</'.$key.'>';
            } else {
                $xml .= '<'.$key.'><![CDATA['.$value.']]></'.$key.'>';
            }
        }
        return $xml.'</xml>';
    } else {
        return ['code' => -1, 'message' => '$params must been an array with elements'];
    }
}

/**
 * generate array from xml
 * @param string $xml
 * @author stone
 */
function xml_to_array($xml){
    if ($xml) {
        libxml_disable_entity_loader(true);
        return json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
    } else {
        return ['code' => -1, 'message' => '$xml can not been empty'];
    }
}

/**
 * generate wechat pay sign
 * @param array $data the data to generate the sign
 * @param string $key the key to generate the sign
 * @author stone
 */
function generate_wxpay_sign($data, $key){
	// step one: sort paramaters by dictionary order
	ksort($data);
	$string = generate_url_params($data, 1, 1, 1, ['sign']);
	// step two: append key to string
	$string = $string . '&key=' .$key;
	// step three: MD5 encrypt
	$string = md5($string);
	// step four: uppercase string
	$result = strtoupper($string);
	return $result;
}
