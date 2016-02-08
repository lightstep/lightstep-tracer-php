<?php

class RuntimeDisableTest extends PHPUnit_Framework_TestCase {

    public function testDisable() {
        $runtime = LightStep::newRuntime("test_group", "1234567890");
        $runtime->disable();

        $runtime->infof("Shouldn't do anything");
        $runtime->warnf("Shouldn't do anything");
        $runtime->errorf("Shouldn't do anything");
        $runtime->fatalf("Shouldn't do anything");

        $span = $runtime->startSpan();
        $span->setOperation("noop_call");
        $span->setEndUserId("ignored_user");
        $span->addTraceJoinId("key_to_an", "unused_value");
        $span->warnf("Shouldn't do anything");
        $span->errorf("Shouldn't do anything");
        $span->finish();
    }
}
