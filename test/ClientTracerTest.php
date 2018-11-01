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
}
