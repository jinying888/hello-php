<?php

$addr = '127.0.0.1';
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

//read_sockets接收client的连接和消息，然后io复用
$read_sockets[intval($sock)] = $sock;
$write_sockets = null;
$except_sockets = null;

$add_read_sockets = [];

while(1){

    if(!empty($add_read_sockets)){
    	$read_sockets = $add_read_sockets;
    }
    socket_select($read_sockets,$write_sockets,$except_sockets,null);

    var_dump($read_sockets);
  
    //if(in_array($sock, $read_sockets)){  
    //    echo "main sock listen is activity";  
    //    $read_sockets[]=socket_accept($sock);  
    //}


    foreach($read_sockets as $k=>$socket){
        if($socket == $sock){
            //主sock接受client的连接
            $client_sock = socket_accept($sock);

            if($client_sock === false){
                echo "a client connected failed\n";
                continue;
            }

            //每个连接有自己的handshake
$hand_shake = 'client'.md5(uniqid(mt_rand(), true));            
$$hand_shake = false;

            echo "收到客户端的连接";
            array_push($add_read_sockets, $client_sock);
            echo "a client connected success\n";
        }else{
            //其他的连接和客户端交互

            $result = socket_read($client_sock,512);
            if($result === false){
                echo "接收数据失败\n";

                //手动关闭客户端,最好清除一下$read_sockets中对应的元素  
                socket_shutdown($socket);
                socket_close($socket);

                unset($read_sockets[$key]);

                continue;
            }

            if (!$$hand_shake) {
                // 如果没有握手，先握手回应
                //doHandShake($socket, $buffer);
                $$hand_shake = true;
                echo "{$hand_shake} shakeHands\n";
            } else {
                // 如果已经握手，直接接受数据，并处理
                echo "shakeHands完成，收到数据{$result}\n";
                //process($socket, $buffer);

            }
            
        }

//    if(!empty($add_read_sockets)){
//	$read_sockets = $add_read_sockets;
//   }

    }

    $add_read_sockets[intval($sock)]=$sock; 
   
    sleep(4);


}


