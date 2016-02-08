<?php

class RuntimeTest extends PHPUnit_Framework_TestCase {

    public function testSpanMaxRecords() {
        $runtime = LightStep::newRuntime("test_group", "1234567890", array(
            'debug_disable_flush' => TRUE,
        ));

        $maxRecords = peek($runtime, "_options")["max_span_records"];

        // Sanity check that the default is not abnormally small (or more
        // likely that the internal variable named hasn't been changed and
        // invalidated this test!)
        $this->assertGreaterThan(10, $maxRecords);

        // Before the max is hit...
        for ($i = 0; $i < $maxRecords; $i++) {
            $span = $runtime->startSpan();
            $span->setOperation("loop_span");
            $span->finish();
            $this->assertEquals($i + 1, count(peek($runtime, "_spanRecords")));
        }
        $this->assertEquals($maxRecords, count(peek($runtime, "_spanRecords")));

        // After the max has been hit...
        for ($i = 0; $i < 10 * $maxRecords; $i++) {
            $span = $runtime->startSpan();
            $span->setOperation("loop_span");
            $span->finish();
            $this->assertEquals($maxRecords, count(peek($runtime, "_spanRecords")));
        }

        $runtime->_discard();
        $this->assertEquals(0, count(peek($runtime, "_spanRecords")));
    }
}
