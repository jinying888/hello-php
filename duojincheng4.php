<?php

//每个5s执行一次打印操作
sleep(1);
$start_time = time();

// 定义一个处理器，接收到SIGALRM信号后只输出一行信息
function signalHandler($signal)
{
    if ($signal == SIGINT) {
        echo 'SIGINT', PHP_EOL;
    }
    if ($signal == SIGALRM ) {
	echo "caught alarm\n";
	pcntl_alarm(5);
	global $start_time;
	$ss = time() - $start_time;
	echo "{$ss}\n";
    }
}

// 信号注册：当接收到SIGINT信号时，调用signalHandler()函数
pcntl_signal(SIGALRM, 'signalHandler');

/**
 * PHP < 5.3 使用
 * 配合pcntl_signal使用,表示每执行一条低级指令，就检查一次信号，如果检测到注册的信号，就调用其信号处理器。
 */
if (!function_exists("pcntl_signal_dispatch")) {
    declare(ticks=1);
}

pcntl_alarm(5);

while(1){

    /**
     * PHP >= 5.3
     * 调用已安装的信号处理器
     * 必须在循环里调用，为了检测是否有新的信号等待dispatching。
     */
    if (function_exists("pcntl_signal_dispatch")) {
        pcntl_signal_dispatch();
    }

    
}


