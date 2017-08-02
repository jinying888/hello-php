<?php

$addr = '172.17.6.147';
$port = 7878;
$read_sockets = [];


$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if($sock === false){
	echo "create socket error\n";
}

$result = socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
if($result === false){
	echo "set option error\n";
}

$result = socket_bind($sock, $addr, $port);
if($result === false){
	echo "bind error\n";
}
$result = socket_listen($sock, 4);
if($result === false) {
    echo "listen error\n";
}else{
    echo "开始监听";
}
//设置$sock为非阻塞
//socket_set_nonblock($sock);

//read_sockets接收client的连接和消息，然后io复用
$read_sockets[intval($sock)] = $sock;
$write_sockets = null;
$except_sockets = null;

$add_read_sockets = [];


while(1){

	//$add_read_sockets保存有效的socket连接（这些连接可能暂时不活动，但是会发送消息）
    if(!empty($add_read_sockets)){
    	$read_sockets = $add_read_sockets;
    }

    socket_select($read_sockets,$write_sockets,$except_sockets,null);
	//socket_select没有收到数据时阻塞进程；有数据时，只返回有数据的socket连接
    var_dump($read_sockets);

  
    //if(in_array($sock, $read_sockets)){  
    //    echo "main sock listen is activity";  
    //    $read_sockets[]=socket_accept($sock);  
    //}

	//遍历所有活动的socket连接
    foreach($read_sockets as $k=>$socket){
        if($socket == $sock){
            //主sock接受client的连接
            $client_sock = socket_accept($sock);

            if($client_sock === false){
                echo "a client connected failed\n";
                continue;
            }

            //每个accept的连接有自己的handshake
            $hand_shake = 'client'.md5(uniqid(mt_rand(), true));
            $$hand_shake = false;

            echo "收到客户端的连接";
            $add_read_sockets[$hand_shake] = $client_sock;
            echo "a client connected success\n";
        }else{
            //其他的accept产生的连接和客户端交互
		echo date('H:i:s',$socket_time[$k]);echo '111';
            //只有这个连接再次请求时，我才知道，才会关闭资源，所以我要设置一个定时器，定时的判断
            //某个连接的在最后一次通信之后，10分钟超时，断开连接
            if( isset($socket_time[$k]) && $socket_time[$k] < time()){
                //手动关闭客户端,最好清除一下$read_sockets中对应的元素
                echo 'timeout';
                socket_close($socket);

                unset($add_read_sockets[$k]);

                continue;
            }

            $result = socket_read($socket,512);

            //$tmp_socket[] = $socket;
            ///$flag = socket_select($tmp_socket,$write_sockets,$except_sockets,null);

            //这种情况说明客户断开了(收到空的数据)
            //如果socket_select返回的连接，发送过来的数据为空，就断开连接
            //还以通过给套接字保存相关的时间戳，通过过期时间判断是否过期，假设10分钟过期
//            if(empty($result)){
//                echo "接收数据失败\n";
//
//                //手动关闭客户端,最好清除一下$read_sockets中对应的元素
//
//                socket_close($socket);
//
//                unset($add_read_sockets[$k]);
//
//                continue;
//            }

            if (!$$hand_shake) {
                // 如果没有握手，先握手回应
                //doHandShake($socket, $buffer);
                if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/",$result,$match)) {
                    $key = base64_encode(sha1($match[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
                    $upgrade  = "HTTP/1.1 101 Switching Protocol\r\n" .
                        "Upgrade: websocket\r\n" .
                        "Connection: Upgrade\r\n" .
                        "Sec-WebSocket-Accept: " . $key . "\r\n\r\n";  //必须以两个回车结尾
                    socket_write($socket, $upgrade, strlen($upgrade));
                    $$hand_shake = true;
                    echo "{$hand_shake} shakeHands\n";
                }
                continue;

            } else {
			echo '222';
                //某个连接的在最后一次通信之后，10分钟超时，断开连接
                $socket_time[$k] = time() + 20;
                // 如果已经握手，直接接受数据，并处理
                //echo date('Y-m-d H:i:s')."shakeHands完成，收到数据{$result}\n";
                //echo 'send data';
                //将websocket数据进行解码
                $result = websocket_decode($result);



                //将信息返回给客户端
                send_to_user($socket,$result);

            }
            
        }

//    if(!empty($add_read_sockets)){
//	$read_sockets = $add_read_sockets;
//   }

    }

    $add_read_sockets[intval($sock)]=$sock; 
   
    sleep(1);


}

function send_to_user($accept, $data){
    //添加头文件信息
$data = frame($data);
socket_write($accept, $data, strlen($data));

}

//要这样编码才客户端可以看到
function frame($payload,$type='text'){
    $frameHead = array();
    $payloadLength = strlen($payload);

    switch ($type) {
        case 'text':
            // first byte indicates FIN, Text-Frame (10000001):
            $frameHead[0] = 129;
            break;

        case 'close':
            // first byte indicates FIN, Close Frame(10001000):
            $frameHead[0] = 136;
            break;

        case 'ping':
            // first byte indicates FIN, Ping frame (10001001):
            $frameHead[0] = 137;
            break;

        case 'pong':
            // first byte indicates FIN, Pong frame (10001010):
            $frameHead[0] = 138;
            break;
    }

    if ($payloadLength > 65535) {
        $ext = pack('NN', 0, $payloadLength);
        $secondByte = 127;
    } elseif ($payloadLength > 125) {
        $ext = pack('n', $payloadLength);
        $secondByte = 126;
    } else {
        $ext = '';
        $secondByte = $payloadLength;
    }

    return $data  = chr($frameHead[0]) . chr($secondByte) . $ext . $payload;
}

//客户端发来的数据要这样解码才可以看到
// 解析数据帧
function websocket_decode($buffer)
{
    $len = $masks = $data = $decoded = null;
    $len = ord($buffer) & 127;

    if ($len === 126)  {
        $masks = substr($buffer, 4, 4);
        $data = substr($buffer, 8);
    } else if ($len === 127)  {
        $masks = substr($buffer, 10, 4);
        $data = substr($buffer, 14);
    } else  {
        $masks = substr($buffer, 2, 4);
        $data = substr($buffer, 6);
    }
    for ($index = 0; $index < strlen($data); $index++) {
        $decoded .= $data[$index] ^ $masks[$index % 4];
    }
    return $decoded;
}
