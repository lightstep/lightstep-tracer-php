<?php

use LightStepBase\Client\ClientTracer;
use LightStepBase\Client\Transports\TransportHTTPJSON;
use LightStepBase\Client\Transports\TransportHTTPPROTO;
use LightStepBase\Client\Transports\TransportUDP;

class ClientTracerTest extends BaseLightStepTest
{

    /**
     * @dataProvider transports
     */
    public function testCorrectTransportSelected($key, $class)
    {

        $tracer = new ClientTracer(['transport' => $key]);

        $this->assertInstanceOf($class, $this->readAttribute($tracer, '_transport'));
    }

    public function transports()
    {

        return [
            'udp' => ['udp', TransportUDP::class],
            'http_json' => ['http_json', TransportHTTPJSON::class],
            'http_proto' => ['http_proto', TransportHTTPPROTO::class],
        ];
    }

    public function testDefaultTracerAttributesAreAdded() {
        $opts = [
            'component_name' => 'test_group',
            'access_token' => '1234567890',
            'debug_disable_flush' => TRUE
        ];
        $tracer = new ClientTracer($opts);
        $attributes = $this->peek($tracer, '_options')['attributes'];

        $this->assertArrayHasKey('lightstep.tracer_platform', $attributes);
        $this->assertSame('php', $attributes['lightstep.tracer_platform']);

        $this->assertArrayHasKey('lightstep.tracer_platform_version', $attributes);
        $this->assertSame(phpversion(), $attributes['lightstep.tracer_platform_version']);

        $this->assertArrayHasKey('lightstep.tracer_version', $attributes);
        $this->assertSame(LIGHTSTEP_VERSION, $attributes['lightstep.tracer_version']);
    }

    public function testSettingCustomTracerAttributes() {
        $opts = [
            'component_name' => 'test_group',
            'access_token' => '1234567890',
            'debug_disable_flush' => TRUE,
            'attributes' => [
                'foo' => 'bar'
            ]
        ];
        $tracer = new ClientTracer($opts);
        $attributes = $this->peek($tracer, '_options')['attributes'];

        $this->assertArrayHasKey('foo', $attributes);
        $this->assertSame($attributes['foo'], 'bar');
    }

    public function testAddingCustomAttributeDoesNotRemoveDefaultAttributes() {
        $opts = [
            'component_name' => 'test_group',
            'access_token' => '1234567890',
            'debug_disable_flush' => TRUE,
            'attributes' => [
                'foo' => 'bar'
            ]
        ];
        $tracer = new ClientTracer($opts);
        $attributes = $this->peek($tracer, '_options')['attributes'];

        $this->assertArrayHasKey('lightstep.tracer_platform', $attributes);
        $this->assertSame('php', $attributes['lightstep.tracer_platform']);

        $this->assertArrayHasKey('lightstep.tracer_platform_version', $attributes);
        $this->assertSame(phpversion(), $attributes['lightstep.tracer_platform_version']);

        $this->assertArrayHasKey('lightstep.tracer_version', $attributes);
        $this->assertSame(LIGHTSTEP_VERSION, $attributes['lightstep.tracer_version']);

        $this->assertArrayHasKey('foo', $attributes);
        $this->assertSame($attributes['foo'], 'bar');
    }

    public function testDefaultTransportSchemeIsTls() {
        $transports = ['http_json', 'http_proto'];

        foreach ($transports as $transportType) {
            $opts = [
                'component_name' => 'test_group',
                'access_token' => '1234567890',
                'debug_disable_flush' => TRUE,
                'transport' => $transportType
            ];
            $tracer = new ClientTracer($opts);
            $transport = $this->peek($tracer, '_transport');
            $tracerOptions = $this->peek($tracer, '_options');
            $transport->ensureConnection($tracerOptions);

            $scheme = $this->peek($transport, '_scheme');
            $this->assertSame('tls://', $scheme);
            $port = $this->peek($transport, '_port');
            $this->assertSame(443, $port);
        }
    }

    public function testCanOverrideTransportScheme() {
        $transports = ['http_json', 'http_proto'];
        $expectedScheme = 'https://';

        foreach ($transports as $transportType) {
            $opts = [
                'component_name' => 'test_group',
                'access_token' => '1234567890',
                'debug_disable_flush' => TRUE,
                'collector_scheme' => $expectedScheme,
                'transport' => $transportType
            ];
            $tracer = new ClientTracer($opts);
            $transport = $this->peek($tracer, '_transport');
            $tracerOptions = $this->peek($tracer, '_options');
            $transport->ensureConnection($tracerOptions);

            $scheme = $this->peek($transport, '_scheme');
            $this->assertSame($expectedScheme, $scheme);
        }
    }

    public function testSendDataInPlainText() {
        $transports = ['http_json', 'http_proto'];

        foreach ($transports as $transportType) {
            $opts = [
                'component_name' => 'test_group',
                'access_token' => '1234567890',
                'debug_disable_flush' => TRUE,
                'transport' => $transportType,
                'collector_secure' => FALSE
            ];
            $tracer = new ClientTracer($opts);
            $transport = $this->peek($tracer, '_transport');
            $tracerOptions = $this->peek($tracer, '_options');
            $transport->ensureConnection($tracerOptions);

            $scheme = $this->peek($transport, '_scheme');
            $this->assertSame('', $scheme); //pfsockopen uses http:// when empty
            $port = $this->peek($transport, '_port');
            $this->assertSame(80, $port);
        }
    }

    public function testInject() {
        $tracer = $this->createTestTracer("test_group", "1234567890");
        $span = $tracer->startSpan("hello/world");

        $carrier = array();
        $tracer->inject($span->getContext(), \OpenTracing\Formats\TEXT_MAP, $carrier);
        $this->assertEquals($carrier['ot-tracer-spanid'], $span->guid());
        $this->assertEquals($carrier['ot-tracer-traceid'], $span->traceGUID());
        $this->assertEquals($carrier['ot-tracer-sampled'], 'true');
        $span->finish();
    }

    public function testInjectAndExtract() {
        $tracer = $this->createTestTracer("test_group", "1234567890");
        $span = $tracer->startSpan("hello/world");

        $carrier = array();
        $tracer->inject($span->getContext(), \OpenTracing\Formats\TEXT_MAP, $carrier);
        $span->finish();

        $spanContext = $tracer->extract(\OpenTracing\Formats\TEXT_MAP, $carrier);
        $this->assertEquals($spanContext->getTraceId(),$span->traceGUID());
        $this->assertEquals($spanContext->getSpanId(),$span->guid());
        $this->assertEquals($spanContext->isSampled(),true);
    }

    public function testInjectExtractWithBaggage() {
        $tracer = $this->createTestTracer("test_group", "1234567890");
        $span = $tracer->startSpan("hello/world");
        $span->addBaggageItem("fruit", "apple");
        $span->addBaggageItem("number", 5);
        $span->addBaggageItem("boolean", true);
        $span->finish();

        $carrier = array();
        $tracer->inject($span->getContext(), \OpenTracing\Formats\TEXT_MAP, $carrier);
        $span->finish();

        $spanContext = $tracer->extract(\OpenTracing\Formats\TEXT_MAP, $carrier);
        $this->assertEquals(count($spanContext->getBaggage()), 3);
        $this->assertEquals($spanContext->getBaggageItem("fruit"), "apple");
        $this->assertEquals($spanContext->getBaggageItem("number"), 5);
        $this->assertEquals($spanContext->getBaggageItem("boolean"), true);
    }

    public function testStartSpanFromSpanContext() {
        $tracer = $this->createTestTracer("test_group", "1234567890");
        $span = $tracer->startSpan("hello/world");

        $carrier = array();
        $tracer->inject($span->getContext(), \OpenTracing\Formats\TEXT_MAP, $carrier);
        $span->finish();

        $spanContext = $tracer->extract(\OpenTracing\Formats\TEXT_MAP, $carrier);
        $child = $tracer->startSpan("hello/child", ["child_of" => $spanContext]);
        $this->assertEquals($child->traceGUID(),$span->traceGUID());
        $this->assertEquals($child->getParentGUID(),$span->guid());
    }

}
