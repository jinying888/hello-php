<?php

$addr = '172.17.6.147';
$port = 7878;



class WebsocketServer
{
    //主连接，用于监听
    public $master;
    //接收client的连接池
    public $sockets = [];
    //client的连接信息
    public $client_users = [];

    public function __construct($addr, $port)
    {
        $sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($sock === false) {
            echo "create socket error\n";
        }
        //1表示接受所有的数据包
        $result = socket_set_option($sock, SOL_SOCKET, SO_REUSEADDR, 1);
        if ($result === false) {
            echo "set option error\n";
        }

        $result = socket_bind($sock, $addr, $port);
        if ($result === false) {
            echo "bind error\n";
        }
        $result = socket_listen($sock, 4);
        if ($result === false) {
            echo "listen error\n";
        } else {
            echo "开始监听";
        }

        $this->master = $sock;

        $this->sockets[] = $this->master;

    }

    public function run()
    {
        while (1) {
            $read_sockets = $this->sockets;
            $write_sockets = null;
            $except_sockets = null;
            //$read_sockets这个数组中存放的是文件描述符。当它有变化（就是有新消息到或者有客户端连接/断开）时，socket_select函数才会返回，继续往下执行。
            //$write是监听是否有客户端写数据，传入NULL是不关心是否有写变化。
            //$except是$sockets里面要被排除的元素，传入NULL是”监听”全部。
            //最后一个参数是超时时间
            //如果为0：则立即结束
            //如果为n>1: 则最多在n秒后结束，如遇某一个连接有新动态，则提前返回
            //如果为null：如遇某一个连接有新动态，则返回
            socket_select($read_sockets, $write_sockets, $except_sockets, null);

            foreach ($read_sockets as $socket) {
                if ($socket == $this->master) {
                    //主sock接受client的连接
                    $client_sock = socket_accept($this->master);

                    if ($client_sock === false) {
                        echo "a client connected failed\n";
                        continue;
                    }

                    //每个accept的连接有自己唯一id
                    $key = 'client' . uniqid(mt_rand(), true);

                    $this->client_users[$key] = [
                        'socket' => $client_sock,  //记录新连接进来client的socket信息
                        'handshake' => false       //标志该socket资源没有完成握手
                    ];

                    $this->sockets[] = $client_sock;

                    echo "a client connected success\n";
                } else {
                    //其他的accept产生的连接和客户端交互
                    $result = socket_read($socket, 512);
                    //根据socket在user_client池里面查找相应的$k,即唯一ID
                    $k = $this->client_search($socket);

                    //收到的数据小于1，client连接关闭
                    if(strlen($result) < 1){
                        $this->send_close($k);
                        continue;
                    }
                    if (!$this->client_users[$k]['handshake']) {
                        // 如果没有握手，先握手回应
                        $this->do_handshake($k,$result);

                    } else {

                        $result = $this->websocket_decode($result);

                        //将信息返回给客户端
                        $this->send_to_user($socket, $result);

                    }

                }

            }

        }
    }

    //关闭client socket
    public function close($k)
    {
        if(empty($this->client_users[$k])){
            return false;
        }
        if(count($this->client_users) < 20){
            return false;
        }
        //断开相应socket
        socket_close($this->client_users[$k]['socket']);
        //删除相应的user信息
        unset($this->client_users[$k]);
        //重新定义sockets连接池
        $this->sockets[] = $this->master;
        foreach($this->client_users as $v){
            $this->sockets[]=$v['socket'];
        }
    }

    //服务端检测到需要断开连接
    public function send_close($k)
    {
        $this->close($k);
    }

    //根据sock在users里面查找相应的$k
    public function client_search($socket)
    {
        foreach ($this->client_users as $k => $v) {
            if ($socket == $v['socket'])
                return $k;
        }
        return false;
    }

    //握手
    public function do_handshake($k, $result)
    {
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $result, $match)) {
            $key = base64_encode(sha1($match[1] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
            $upgrade = "HTTP/1.1 101 Switching Protocol\r\n" .
                "Upgrade: websocket\r\n" .
                "Connection: Upgrade\r\n" .
                "Sec-WebSocket-Accept: " . $key . "\r\n\r\n";  //必须以两个回车结尾
            socket_write($this->client_users[$k]['socket'], $upgrade, strlen($upgrade));
            $this->client_users[$k]['handshake'] = true;
            return true;
        }
        return false;
    }


    public function send_to_user($accept, $data){
        //添加头文件信息
        $data = $this->frame($data);
        //如果出现了连接不能写的情况，也说明客户端断开了连接
        socket_write($accept, $data, strlen($data));
    }

    //要这样编码才客户端可以看到
    public function frame($payload,$type='text'){
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
    public function websocket_decode($buffer)
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

}


$ws = new WebsocketServer($addr,$port);
$ws->run();
