<?php


// 检查是否已经超时
function check_timeout($time_start){
    // 5秒超时
    $timeout = 5;
    if(time()-$time_start > $timeout){
        exit("超时{$timeout}秒\n");
    }
}

// Set up a tick handler
register_tick_function("check_timeout",time());


// Run a block of code, throw a tick every 2nd statement
declare(ticks=1) {
    
    for (;;) {
        echo "hello world\n";
    }
}
