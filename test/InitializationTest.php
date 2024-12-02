<?php

class InitializationTest extends BaseLightStepTest {

    /*
        If data collection is started before the runtime is configuration is
        completed, the runtime should buffer that data until the init call.
     */
    /**
     * @doesNotPerformAssertions
     */
    public function testOutOfOrderInitializationDoesntFail() {
        $opts = [
            "debug_disable_flush" => "true"
        ];
        $runtime = LightStep::newTracer(NULL, NULL, $opts);
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
        $opts = [
            "debug_disable_flush" => "true"
        ];
        $runtime = LightStep::newTracer(NULL, NULL, $opts);
        $span = $runtime->startSpan("test_span");

        $this->assertGreaterThan(0, $this->peek($runtime, "_options")['max_log_records']);
        $this->assertGreaterThan(0, $this->peek($runtime, "_options")['max_span_records']);

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
        $opts = [
            "debug_disable_flush" => "true"
        ];
        $runtime = LightStep::newTracer(NULL, NULL, $opts);
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


        $this->assertEquals(2, count($this->peek($runtime, "_spanRecords")));
        $runtime->flush();
    }
}
