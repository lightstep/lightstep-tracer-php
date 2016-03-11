<?php

class InitializationTest extends PHPUnit_Framework_TestCase {

    /*
        If data collection is started before the runtime is configuration is
        completed, the runtime should buffer that data until the init call.
     */
    public function testOutOfOrderInitializationDoesntFail() {
        $runtime = LightStep::newTracer(NULL, NULL);
        $span = $runtime->startSpan("test_span");
        $span->infof("log000");

        $span2 = $runtime->startSpan("operation/000");
        $span2->finish();

        $runtime->flush();

        $runtime->options(array(
            'component_name' => 'init_test_group',
            'access_token' => '1234567890',
        ));
        $span->finish();
        $runtime->flush();
    }

    public function testMultipleInitCalls() {
        $runtime = LightStep::newTracer(NULL, NULL);
        $span = $runtime->startSpan("test_span");

        $this->assertGreaterThan(0, peek($runtime, "_options")['max_log_records']);
        $this->assertGreaterThan(0, peek($runtime, "_options")['max_span_records']);

        for ($i = 0; $i < 100; $i++) {
            $span->infof("log%03d", 3 * $i);

            // Redundant calls are fine as long as the configuration
            // is the same
            $runtime->options(array(
                'component_name'   => 'init_test_group',
                'access_token' => '1234567890',
            ));

           $span->infof("log%03d", 7 * $i);
        }
        $span->finish();
    }

    public function testSpanBufferingBeforeInit() {
        $runtime = LightStep::newTracer(NULL, NULL);
        $span = $runtime->startSpan("first");
        $span->infof('Hello %s', 'World');
        $span->finish();

        $runtime->options(array(
            'component_name'   => 'init_test_group',
            'access_token' => '1234567890',
        ));

        $span = $runtime->startSpan("second");
        $span->infof('Hola %s', 'Mundo');
        $span->finish();


        $this->assertEquals(2, count(peek($runtime, "_spanRecords")));
        $runtime->flush();
    }
}
