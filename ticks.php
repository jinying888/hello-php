<?php

// A function that records the time when it is called
function profile()
{
    echo "profile function is called\n";
}

// Set up a tick handler
register_tick_function("profile");

// Initialize the function before the declare block
//profile();

// Run a block of code, throw a tick every 2nd statement
declare(ticks=1) {
    for ($x = 0; $x < 5; $x++) {
        echo "hello world\n";
    }
}
