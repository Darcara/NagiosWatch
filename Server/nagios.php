<?php

error_reporting(-1);

try{

if(!function_exists('socket_create')) throw new Exception('The PHP function socket_create is not available. Maybe the sockets module is missing in your PHP installation.');
if(!function_exists('json_encode')) throw new Exception('No json support');

$conf = Array(
    'socketPath'       => '/usr/local/nagios/var/rw/live',
);
$LIVE = null;

connectSocket();
$data = queryLivestatus("GET hosts");
closeSocket();

$read = readSocket(16);

response(true, null, $data);

} catch(Exception $e) {
	response(false, 'Error: '.$e->getMessage(), null);
	closeSocket();
	exit(1);
}

function queryLivestatus($query) {
    global $LIVE;
    
    // Query to get a json formated array back
    // Use fixed16 header
    socket_write($LIVE, $query . "\nOutputFormat:json\nResponseHeader: fixed16\n\n");
    
    // Read 16 bytes to get the status code and body size
    $read = readSocket(16);
    
    if($read === false)
        throw new Exception('Problem while reading from socket: '.socket_strerror(socket_last_error($LIVE)));
    
    // Extract status code
    $status = substr($read, 0, 3);
    
    // Extract content length
    $len = intval(trim(substr($read, 4, 11)));
    
    // Read socket until end of data
    $read = readSocket($len);
    
    if($read === false)
        throw new Exception('Problem while reading from socket: '.socket_strerror(socket_last_error($LIVE)));
    
    // Catch errors (Like HTTP 200 is OK)
    if($status != "200")
        throw new Exception('Problem while reading from socket: '.$read);
    
    // Catch problems occured while reading? 104: Connection reset by peer
    if(socket_last_error($LIVE) == 104)
        throw new Exception('Problem while reading from socket: '.socket_strerror(socket_last_error($LIVE)));
    
    // Decode the json response
    $obj = json_decode(utf8_encode($read));
    
    // json_decode returns null on syntax problems
    if($obj === null)
        throw new Exception('The response has an invalid format');
    else
        return $obj;
}

function readSocket($len) {
    global $LIVE;
    $offset = 0;
    $socketData = '';
    
    while($offset < $len) {
        if(($data = @socket_read($LIVE, $len - $offset)) === false)
            return false;
    
        $dataLen = strlen ($data);
        $offset += $dataLen;
        $socketData .= $data;
        
        if($dataLen == 0)
            break;
    }
    
    return $socketData;
}


function response($success, $errorMessage, $content) {
    header('Content-type: application/json');

    $data = new stdClass;
    $data->success = $success;
    $data->error = $errorMessage;
    $data->content = json_encode($content);

    echo json_encode($data);
}

function connectSocket() {
    global $conf, $LIVE;
    $LIVE = socket_create(AF_UNIX, SOCK_STREAM, 0);
    
    if($LIVE == false) {
        throw new Exception('Could not create livestatus socket connection.');
    }
    
   $result = socket_connect($LIVE, $conf['socketPath']);
    
    if($result == false) {
        throw new Exception('Unable to connect to livestatus socket.');
    }
}
function closeSocket() {
    global $LIVE;
    @socket_close($LIVE);
    $LIVE = null;
}
?>
