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
                'collector_secure' => true,
                'transport' => $transportType
            ];
            $tracer = new ClientTracer($opts);
            $transport = $this->peek($tracer, '_transport');
            $tracerOptions = $this->peek($tracer, '_options');
            $transport->ensureConnection($tracerOptions);

            $scheme = $this->peek($transport, '_scheme');
            $this->assertSame('tls://', $scheme);
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
                'collector_secure' => true,
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
}
