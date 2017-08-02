<?php

$addr = '172.17.6.147';
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


// 信号注册：当接收到SIGINT信号时，调用匿名函数处理
pcntl_signal(SIGINT, function($signal) use($sock){
    if ($signal == SIGINT ) {
        echo 'receive ctrl c';
        socket_shutdown($sock);
        socket_close($sock);
        exit;
    }
});

while(1){
pcntl_signal_dispatch();
$send_str = "hello, i am client, repeat";
$client_sock = socket_write($sock,$send_str);
echo "向服务端发送数据{$send_str}\n";
sleep(2);
}


//收到信号Ctrl+C可以关闭连接
socket_close($sock);



