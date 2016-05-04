<?php

class SpanTest extends PHPUnit_Framework_TestCase {

    public function testSpanSetOperation() {
        $tracer = LightStep::newTracer("test_group", "1234567890");
        $span = $tracer->startSpan("server/query");
        $span->finish();

        $this->assertEquals(peek($span, "_operation"), "server/query");
    }

    public function testSpanStartEndMicros() {
        $tracer = LightStep::newTracer("test_group", "1234567890");

        $sum = 0;
        for ($i = 0; $i < 50; $i++) {
            $span = $tracer->startSpan("start_end");
            usleep(500);
            $span->finish();

            $start = peek($span, "_startMicros");
            $end = peek($span, "_endMicros");
            $delta = $end - $start;
            $sum += $delta;

            $this->assertGreaterThan(0, $delta);
        }
        $avg = $sum / 50;

        // Is the average at least *reasonable*? We're not demanding
        // a lot of precision from usleep() here.
        $this->assertGreaterThan(100, $avg);
        $this->assertLessThan(1000, $avg);
    }

    public function testSpanJoinIds() {
        $tracer = LightStep::newTracer("test_group", "1234567890");
        $span = $tracer->startSpan("join_id_span");

        $span->addTraceJoinId("number", "one");
        $this->assertEquals(count(peek($span, "_joinIds")), 1);

        $span->setEndUserId("mr_jones");
        $this->assertEquals(count(peek($span, "_joinIds")), 2);
    }

    public function testSpanLogging() {
        $tracer = LightStep::newTracer("test_group", "1234567890");
        $span = $tracer->startSpan("log_span");
        $span->infof("Test %d %f %s", 1, 2.0, "three");
        $span->warnf("Test %d %f %s", 1, 2.0, "three");
        $span->errorf("Test %d %f %s", 1, 2.0, "three");
        $span->finish();
    }

    public function testSpanAttributes() {
        $tracer = LightStep::newTracer("test_group", "1234567890");
        $span = $tracer->startSpan("attributes_span");
        $span->setTag("test_attribute_1", "value 1");
        $span->setTag("test_attribute_2", "value 2");

        $this->assertEquals(count(peek($span, "_tags")), 2);

        $span->setTag("test_attribute_3", "value 3");

        $this->assertEquals(count(peek($span, "_tags")), 3);

        $span->finish();
    }

    public function testStartSpanWithParent() {
        $tracer = LightStep::newTracer('test_group', '1234567890');

        $parent = $tracer->startSpan('parent');
        $this->assertTrue(strlen($parent->traceGUID()) > 0);
        $this->assertTrue(strlen($parent->guid()) > 0);

        $child = $tracer->startSpan('child', array('parent' => $parent));
        $this->assertEquals($child->traceGUID(), $parent->traceGUID());
        $this->assertEquals($child->getParentGUID(), $parent->guid());

        $child->finish();
        $parent->finish();
    }

    public function testSetParent() {
        // NOTE: setParent() is not part of the OpenTracing API. (Reminder this
        // is a unit test so non-API calls are ok!)
        $tracer = LightStep::newTracer('test_group', '1234567890');

        $parent = $tracer->startSpan('parent');
        $this->assertTrue(strlen($parent->traceGUID()) > 0);
        $this->assertTrue(strlen($parent->guid()) > 0);

        $child = $tracer->startSpan('child');
        $child->setParent($parent);
        $this->assertEquals($child->traceGUID(), $parent->traceGUID());
        $this->assertEquals($child->getParentGUID(), $parent->guid());

        $child->finish();
        $parent->finish();
    }

    public function testSpanThriftRecord() {
        $tracer = LightStep::newTracer("test_group", "1234567890");
        $span = $tracer->startSpan("hello/world");
        $span->setEnduserId("dinosaur_sr");
        $span->finish();

        // Transform the object into a associative array
        $arr = json_decode(json_encode($span->toThrift()), TRUE);
        $this->assertTrue(is_string($arr["span_guid"]));
        $this->assertTrue(is_string($arr["trace_guid"]));
        $this->assertTrue(is_string($arr["runtime_guid"]));
        $this->assertTrue(is_string($arr["span_name"]));
        $this->assertEquals(1, count($arr["join_ids"]));
        $this->assertTrue(is_string($arr["join_ids"][0]["TraceKey"]));
        $this->assertTrue(is_string($arr["join_ids"][0]["Value"]));
    }

    public function testInjectJoin() {
        $tracer = LightStep::newTracer("test_group", "1234567890");
        $span = $tracer->startSpan("hello/world");

        $carrier = array();
        $tracer->inject($span, LIGHTSTEP_FORMAT_TEXT_MAP, $carrier);
        $this->assertEquals($carrier['ot-tracer-spanid'], $span->guid());
        $this->assertEquals($carrier['ot-tracer-traceid'], $span->traceGUID());
        $this->assertEquals($carrier['ot-tracer-sampled'], 'true');
        $span->finish();

        $child = $tracer->join('child', LIGHTSTEP_FORMAT_TEXT_MAP, $carrier);
        $this->assertEquals($child->traceGUID(), $span->traceGUID());
        $this->assertEquals($child->getParentGUID(), $span->guid());
        $child->finish();
    }
}
