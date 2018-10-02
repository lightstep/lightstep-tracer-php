<?php

require __DIR__ . '/vendor/autoload.php';

LightStep::initGlobalTracer('examples/trivial_process', '{your_access_token}');

$span = LightStep::startSpan("trivial/loop");
for ($i = 0; $i < 10; $i++) {
    $span->logEvent("loop_iteration", $i);
    echo "The current unix time is " . time() . "\n";
    usleep(1e5);
    $child = LightStep::startSpan("child_span", array(parent => $span));
    usleep(2e5);
    $child->logEvent("hello world");
    $child->finish();
    usleep(1e5);
}
$span->finish();
