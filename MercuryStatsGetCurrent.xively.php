#!/usr/bin/env php-cli
<?php
/**
* Get current data from Mercury 200.02(usb-rs485) and put to xively.com
* https://xively.com/feeds/19249442
*
*
* @author Sergey Krasnov <me@sergeykrasnov.ru>
*/
ini_set('max_execution_time', 10);

$Xively_apikey = '';
$Xively_feedurl = '"';


// Parameters for port
exec("/bin/stty -F /dev/ttyUSB0 9600 ignbrk -brkint -icrnl -imaxbel -opost -onlcr -isig -icanon -iexten -echo -echoe -echok -echoctl -echoke noflsh -ixon -crtscts");

// Open port
$fp = fopen("/dev/ttyUSB0", "r+");
if (!$fp) {
        echo "Error";die();
}

// Sent command to device
stream_set_blocking($fp,1);
fwrite($fp, "\x00\x06\x47\x5E\x63\xEC\xD4");   // string to receiving current amperage,voltage with corrected CRC and device address

// Read answer from device with 500ms timeout
$result = '';
$c = '';
stream_set_blocking($fp,0);
$timeout=microtime(1)+0.5;
while (microtime(1)<$timeout) {
        $c=fgetc($fp);
        if($c === false){
                        usleep(5);
                        continue;
        }
        $result .= $c;
}
fclose($fp);

// split answer data on parts
$crc = substr($result,-2);                       // crc16  of answer
$addr = hexdec(bin2hex(substr($result,1,3)));    // address of power device
$answer_cmd = substr($result,4,-2);              // answered command
$answer = substr($result,5,-2);                  // answer string

// Format and output data
$voltage = bin2hex(substr($answer,0,2))/10;
$amperage = bin2hex(substr($answer,2,2))/100;
$energy = bin2hex(substr($answer,4,3))/1000;
$json = <<<EOF
{
"version":"1.0.0",
"datastreams":[
        {"id":"voltage", "current_value":"$voltage"},
        {"id":"amperage", "current_value":"$amperage"},
        {"id":"energy", "current_value":"$energy"}
]
}
EOF;

exec("/opt/bin/curl --request PUT --data-binary '$json' --header 'X-ApiKey: $Xively_apikey' --insecure '$Xively_feedurl'");