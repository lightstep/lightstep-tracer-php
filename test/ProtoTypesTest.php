<?php

define("ACCESS_TOKEN", "12345");
use Lightstep\Collector\Reference\Relationship;
use LightStepBase\Client\Util;

class ProtoTypesTest extends BaseLightStepTest {

    public function testAuthToProto() {
        $auth = new \LightStepBase\Client\Auth(ACCESS_TOKEN);
        $protoAuth = $auth->toProto();

        $this->assertTrue($protoAuth instanceof \Lightstep\Collector\Auth);
        $this->assertEquals(ACCESS_TOKEN, $protoAuth->getAccessToken());
    }

    public function testClientSpanToProto() {
        $tracer = new \LightStepBase\Client\ClientTracer();
        $span = new \LightStepBase\Client\ClientSpan($tracer, 5);
        $span->overwriteOperationName("my_operation");
        $span->setStartMicros(1538476581123456);
        $span->setEndMicros(1538476581124456);
        $span->setParentGUID("513887a3b3f460d8");
        $span->setTag("my_key", "my_value");
        $span->logEvent("something happened");
        $protoSpan = $span->toProto();

        $this->assertTrue($protoSpan instanceof \Lightstep\Collector\Span);

        $protoSpanContext = $protoSpan->getSpanContext();
        $this->assertTrue($protoSpanContext instanceof \Lightstep\Collector\SpanContext);

//        Should be equal, but there is a known issue https://github.com/protocolbuffers/protobuf/issues/5216
//        $this->assertEquals(\LightStepBase\Client\Util::hexdec($span->traceGUID()), $protoSpanContext->getTraceId());
//        $this->assertEquals(\LightStepBase\Client\Util::hexdec($span->guid()), $protoSpanContext->getSpanId());

        $this->assertEquals(0, count($protoSpanContext->getBaggage()));

        $this->assertEquals("my_operation", $protoSpan->getOperationName());
        $this->assertEquals(1538476581, $protoSpan->getStartTimestamp()->getSeconds());
        $this->assertEquals(12345600, $protoSpan->getStartTimestamp()->getNanos());
        $this->assertEquals(1000, $protoSpan->getDurationMicros());

        $references = $protoSpan->getReferences();
        $this->assertEquals(1, count($references));
        $this->assertEquals(Relationship::CHILD_OF, $references[0]->getRelationship());
        $parentContext = $references[0]->getSpanContext();
        $this->assertTrue($parentContext instanceof \Lightstep\Collector\SpanContext);
        $this->assertEquals(\LightStepBase\Client\Util::hexdec($span->getParentGUID()), $parentContext->getSpanId());
        $this->assertEquals(0, count($parentContext->getBaggage()));

        $protoTags = $protoSpan->getTags();
        $this->assertEquals(1, count($protoTags));
        $this->assertEquals("my_key", $protoTags[0]->getKey());
        $this->assertEquals("my_value", $protoTags[0]->getStringValue());

        $protoLogs = $protoSpan->getLogs();
        $this->assertEquals(1, count($protoLogs));
        $protoLog = $protoLogs[0];
        $this->assertTrue($protoLog->getTimestamp()->getSeconds() > 0);
        $fields = $protoLog->getFields();
        $this->assertEquals(3, count($fields));
        $this->assertEquals("span_guid", $fields[0]->getKey());
        $this->assertEquals($span->guid(), $fields[0]->getStringValue());
        $this->assertEquals("event", $fields[1]->getKey());
        $this->assertEquals("something happened", $fields[1]->getStringValue());
        $this->assertEquals("runtime_guid", $fields[2]->getKey());
        $this->assertEquals($span->guid(), $fields[2]->getStringValue());
    }

    public function testKeyValueToProto() {
        $keyValue = new \LightStepBase\Client\KeyValue("aKey", "aValue");
        $protoKeyValue = $keyValue->toProto();

        $this->assertTrue($protoKeyValue instanceof \Lightstep\Collector\KeyValue);
        $this->assertEquals("aKey", $protoKeyValue->getKey());
        $this->assertEquals("aValue", $protoKeyValue->getStringValue());
    }

    public function testLogRecordToProtoNoTimestamp() {
        $fields = [
            'level' => 1,
            'message' => "a log message",
        ];
        $logRecord = new \LightStepBase\Client\LogRecord($fields);
        $protoLog = $logRecord->toProto();

        $this->assertTrue($protoLog instanceof \Lightstep\Collector\Log);
        $protoFields = $protoLog->getFields();
        $this->assertEquals(count($fields), count($protoFields));

        $this->assertEquals("level", $protoFields[0]->getKey());
        $this->assertEquals("1", $protoFields[0]->getStringValue());
        $this->assertEquals("message", $protoFields[1]->getKey());
        $this->assertEquals("a log message", $protoFields[1]->getStringValue());

        $this->assertNotNull($protoLog->getTimestamp());
    }

    public function testLogRecordToProtoWithTimestamp() {
        $fields = [
            'level' => 1,
            'message' => "a log message",
            'timestamp_micros' => 1538476581123456,
        ];
        $logRecord = new \LightStepBase\Client\LogRecord($fields);
        $protoLog = $logRecord->toProto();

        $this->assertTrue($protoLog instanceof \Lightstep\Collector\Log);
        $protoFields = $protoLog->getFields();
        $this->assertEquals(count($fields)-1, count($protoFields));

        $this->assertEquals("level", $protoFields[0]->getKey());
        $this->assertEquals("1", $protoFields[0]->getStringValue());
        $this->assertEquals("message", $protoFields[1]->getKey());
        $this->assertEquals("a log message", $protoFields[1]->getStringValue());

        $this->assertNotNull($protoLog->getTimestamp());
        $this->assertEquals(1538476581, $protoLog->getTimestamp()->getSeconds());
        $this->assertEquals(123456, $protoLog->getTimestamp()->getNanos());
    }

    public function testReporterToProto() {
        $guid = "60f81da67a8ff833";
        $start = 1538476581123456;
        $group = "my_component";
        $attr = new \LightStepBase\Client\KeyValue("my_key", "my_value");
        $runtime = new \LightStepBase\Client\Runtime($guid, $start, $group, [$attr]);
        $protoReporter = $runtime->toProto();

        $this->assertTrue($protoReporter instanceof \Lightstep\Collector\Reporter);
        $this->assertEquals(6987367422723356723, $protoReporter->getReporterId());
        $this->assertEquals(2, count($protoReporter->getTags()));
        $this->assertEquals("my_key", $protoReporter->getTags()[0]->getKey());
        $this->assertEquals("my_value", $protoReporter->getTags()[0]->getStringValue());
        $this->assertEquals("lightstep.component_name", $protoReporter->getTags()[1]->getKey());
        $this->assertEquals("my_component", $protoReporter->getTags()[1]->getStringValue());
    }

    public function testReportRequestToProto() {
        $auth = new \LightStepBase\Client\Auth(ACCESS_TOKEN);
        $guid = "60f81da67a8ff833";
        $start = 1538476581123456;
        $group = "my_component";
        $attr = new \LightStepBase\Client\KeyValue("my_key", "my_value");
        $runtime = new \LightStepBase\Client\Runtime($guid, $start, $group, [$attr]);

        $tracer = new \LightStepBase\Client\ClientTracer();
        $span = new \LightStepBase\Client\ClientSpan($tracer, 5);
        $span->overwriteOperationName("my_operation");
        $span->setStartMicros(1538476581123456);
        $span->setEndMicros(1538476581124456);
        $span->setParentGUID("513887a3b3f460d8");
        $span->setTag("my_key", "my_value");
        $span->logEvent("something happened");

        $counters = ['dropped_counters' => 22];

        $start = 1538476581123456;
        $now = Util::nowMicros();
        $reportRequest = new \LightStepBase\Client\ReportRequest($runtime, $start, $now, [$span], $counters);

        $protoReportRequest = $reportRequest->toProto($auth);
        $this->assertTrue($protoReportRequest instanceof \Lightstep\Collector\ReportRequest);

        // verify reporter
        $protoReporter = $protoReportRequest->getReporter();
        $this->assertTrue($protoReporter instanceof \Lightstep\Collector\Reporter);
        $this->assertEquals(6987367422723356723, $protoReporter->getReporterId());
        $this->assertEquals(2, count($protoReporter->getTags()));
        $this->assertEquals("my_key", $protoReporter->getTags()[0]->getKey());
        $this->assertEquals("my_value", $protoReporter->getTags()[0]->getStringValue());
        $this->assertEquals("lightstep.component_name", $protoReporter->getTags()[1]->getKey());
        $this->assertEquals("my_component", $protoReporter->getTags()[1]->getStringValue());

        // verify timestamp offset micros
        $this->assertEquals(0, $protoReportRequest->getTimestampOffsetMicros());

        // verify auth
        $protoAuth = $protoReportRequest->getAuth();
        $this->assertTrue($protoAuth instanceof \Lightstep\Collector\Auth);
        $this->assertEquals(ACCESS_TOKEN, $protoAuth->getAccessToken());

        // verify internal metrics
        $protoMetrics = $protoReportRequest->getInternalMetrics();
        $this->assertTrue($protoMetrics instanceof \Lightstep\Collector\InternalMetrics);
        $this->assertEquals(0, count($protoMetrics->getLogs()));
        $this->assertEquals(0, $protoMetrics->getDurationMicros());
        $this->assertEquals(0, $protoMetrics->getStartTimestamp());
        $this->assertEquals(0, count($protoMetrics->getGauges()));
        $this->assertEquals(1, count($protoMetrics->getCounts()));
        $count = $protoMetrics->getCounts()[0];
        $this->assertEquals("dropped_counters", $count->getName());
        $this->assertEquals(22, $count->getIntValue());

        // verify Spans
        $protoSpans = $protoReportRequest->getSpans();
        $this->assertEquals(1, count($protoSpans));
        $protoSpan = $protoSpans[0];
        $this->assertTrue($protoSpan instanceof \Lightstep\Collector\Span);

        $protoSpanContext = $protoSpan->getSpanContext();
        $this->assertTrue($protoSpanContext instanceof \Lightstep\Collector\SpanContext);

//        Should be equal, but there is a known issue https://github.com/protocolbuffers/protobuf/issues/5216
//        $this->assertEquals(\LightStepBase\Client\Util::hexdec($span->traceGUID()), $protoSpanContext->getTraceId());
//        $this->assertEquals(\LightStepBase\Client\Util::hexdec($span->guid()), $protoSpanContext->getSpanId());

        $this->assertEquals(0, count($protoSpanContext->getBaggage()));

        $this->assertEquals("my_operation", $protoSpan->getOperationName());
        $this->assertEquals(1538476581, $protoSpan->getStartTimestamp()->getSeconds());
        $this->assertEquals(12345600, $protoSpan->getStartTimestamp()->getNanos());
        $this->assertEquals(1000, $protoSpan->getDurationMicros());

        $references = $protoSpan->getReferences();
        $this->assertEquals(1, count($references));
        $this->assertEquals(Relationship::CHILD_OF, $references[0]->getRelationship());
        $parentContext = $references[0]->getSpanContext();
        $this->assertTrue($parentContext instanceof \Lightstep\Collector\SpanContext);
        $this->assertEquals(\LightStepBase\Client\Util::hexdec($span->getParentGUID()), $parentContext->getSpanId());
        $this->assertEquals(0, count($parentContext->getBaggage()));

        $protoTags = $protoSpan->getTags();
        $this->assertEquals(1, count($protoTags));
        $this->assertEquals("my_key", $protoTags[0]->getKey());
        $this->assertEquals("my_value", $protoTags[0]->getStringValue());

        $protoLogs = $protoSpan->getLogs();
        $this->assertEquals(1, count($protoLogs));
        $protoLog = $protoLogs[0];
        $this->assertTrue($protoLog->getTimestamp()->getSeconds() > 0);
        $fields = $protoLog->getFields();
        $this->assertEquals(3, count($fields));
        $this->assertEquals("span_guid", $fields[0]->getKey());
        $this->assertEquals($span->guid(), $fields[0]->getStringValue());
        $this->assertEquals("event", $fields[1]->getKey());
        $this->assertEquals("something happened", $fields[1]->getStringValue());
        $this->assertEquals("runtime_guid", $fields[2]->getKey());
        $this->assertEquals($span->guid(), $fields[2]->getStringValue());
    }
}
