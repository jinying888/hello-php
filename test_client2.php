<?php

$addr = '127.0.0.1';
$port = 7878;

$sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if($sock === false){
	echo "create socket error\n";
}
if(false === socket_connect ($sock , $addr, $port)){
    echo "连接失败";
}else{
    echo "connect success\n";
}


$send_str = "hello, i am client 2";
$client_sock = socket_write($sock,$send_str);
echo "向服务端发送数据{$send_str}\n";

while(1){

$send_str = "hello, i am client 2, repeat";
$client_sock = socket_write($sock,$send_str);
echo "向服务端发送数据{$send_str}\n";
sleep(2);
}

socket_close($sock);



