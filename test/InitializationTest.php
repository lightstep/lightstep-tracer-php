<?php

class InitializationTest extends PHPUnit_Framework_TestCase {

    /*
        If data collection is started before the runtime is configuration is
        completed, the runtime should buffer that data until the init call.
     */
    public function testOutOfOrderInitializationDoesntFail() {

        $runtime = LightStep::newRuntime(NULL, NULL);

        $runtime->infof("log000");

        $span = $runtime->startSpan();
        $span->setOperation("operation/000");
        $span->finish();

        $runtime->flush();

        $runtime->options(array(
            'group_name' => 'init_test_group',
            'access_token' => '1234567890',
        ));
        $runtime->flush();
    }

    public function testMultipleInitCalls() {

        $runtime = LightStep::newRuntime(NULL, NULL);
        $this->assertGreaterThan(0, peek($runtime, "_options")['max_log_records']);
        $this->assertGreaterThan(0, peek($runtime, "_options")['max_span_records']);

        for ($i = 0; $i < 100; $i++) {
            $runtime->infof("log%03d", 3 * $i);

            // Redundant calls are fine as long as the configuration
            // is the same
            $runtime->options(array(
                'group_name'   => 'init_test_group',
                'access_token' => '1234567890',
            ));

           $runtime->infof("log%03d", 7 * $i);
        }
    }

    public function testSpanBufferingBeforeInit() {
        $runtime = LightStep::newRuntime(NULL, NULL);
        $span = $runtime->startSpan();
        $span->setOperation("first");
        $span->infof('Hello %s', 'World');
        $span->finish();

        $runtime->options(array(
            'group_name'   => 'init_test_group',
            'access_token' => '1234567890',
        ));

        $span = $runtime->startSpan();
        $span->setOperation("second");
        $span->infof('Hola %s', 'Mundo');
        $span->finish();


        $this->assertEquals(2, count(peek($runtime, "_spanRecords")));
        $runtime->flush();
    }
}
