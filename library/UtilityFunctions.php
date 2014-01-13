<?php

/**
 * This function is a pretty var_dump
 * @param var $thing
 * @return void
 */
function d($thing) {
    echo '<pre>' . PHP_EOL;
    var_dump($thing);
    echo '</pre>' . PHP_EOL;
}