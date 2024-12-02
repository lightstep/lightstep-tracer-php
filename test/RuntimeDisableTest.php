<?php

class RuntimeDisableTest extends BaseLightStepTest {

    /**
     * @doesNotPerformAssertions
     */
    public function testDisable() {
        $runtime = LightStep::newTracer("test_group", "1234567890");
        $runtime->disable();

        $span = $runtime->startSpan("noop_call");
        $span->setEndUserId("ignored_user");
        $span->addTraceJoinId("key_to_an", "unused_value");
        $span->warnf("Shouldn't do anything");
        $span->errorf("Shouldn't do anything");
        $span->fatalf("Shouldn't do anything");
        $span->finish();
    }
}
