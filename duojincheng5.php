<?php

//使用了alarm定时发送信号，然后处理信号，
//程序在执行了11s之后，主进程退出执行，

$start = time();
$chongzhi_url = 'http://172.17.6.147';
$n=0;

// 11s之后主进程退出
$start_time = time();

// 定义一个处理器，接收到SIGALRM信号后只输出一行信息
function signalHandler($signal)
{
    if ($signal == SIGINT) {
        echo 'SIGINT', PHP_EOL;
    }
    if ($signal == SIGALRM ) {
        echo "caught alarm\n";
        global $start_time;
        $ss = time() - $start_time;
        echo "{$ss}\n";
	//通知系统内核，忽视子进程退出的信号，由内核回收子进程，避免了再Zz进程
        pcntl_signal(SIGCHLD,  SIG_IGN);
	exit('tuichu chengxu');
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

pcntl_alarm(11);


while(1){


    /**
     * PHP >= 5.3
     * 调用已安装的信号处理器
     * 必须在循环里调用，为了检测是否有新的信号等待dispatching。
     */
    if (function_exists("pcntl_signal_dispatch")) {
        pcntl_signal_dispatch();
    }


    $childs = array();
// Fork10个子进程
    for ($i = 0; $i < 10; $i++) {
        $pid = pcntl_fork();
        if ($pid == -1)
            die('Could not fork');

        if ($pid) {

            //不在这里wait子进程，是因为会等待子进程，导致阻塞父进程，影响执行效率
            //     pcntl_wait($status);
            echo "parent \n";
            $childs[] = $pid;

        } else {
            //假设子进程执行了1s
            sleep(5);
            echo $i."zijinch \n";
	    $ch = curl_init();
		
		curl_setopt($ch, CURLOPT_URL, $chongzhi_url );
                       curl_setopt($ch, CURLOPT_HEADER, TRUE);
                      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        //超时时间
                        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                        $output = curl_exec($ch);
                        //var_dump($output);
                        curl_close($ch);
            // 子进程需要exit,防止子进程也进入for循环
            exit();
        }
    }

    //子进程已经exit了，所以下面都是父进程执行的
    $n++;
    echo "\n";
    echo $n;
    echo "\n";

    //回收for循环建立的子进程 start
    $cycle_st = time();
    while (count($childs) > 0) {
        foreach ($childs as $key => $pid) {
            $res = pcntl_waitpid($pid, $status, WNOHANG);

//-1代表error, 大于0代表子进程已退出,返回的是子进程的pid,非阻塞时0代表没取到退出子进程
            if ($res == -1 || $res > 0)
                unset($childs[$key]);
        }

        if(time() - $cycle_st > 3){
            echo "\n---------回收子进程超时----------\n";
            sleep(1);
            //跳出回收while
            break;
        }

        usleep(200);
    }

    sleep(1);

    //回收for循环建立的子进程 end
    echo "\n+++++++++++++++++++++++++++++\n";
    $end = time();

    $avg = ($end - $start)/floatval($n);
    if($end - $start + ceil($avg) > 17){
        exit;
    }

    echo "\n------------------------------\n";

}
