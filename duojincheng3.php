<?php

$start = time();
$chongzhi_url = 'http://172.17.6.147';
$n=0;

while(1){

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
