<?php

class LightStepTest extends BaseLightStepTest {

    public function testGetInstance() {
        $inst = LightStep::getInstance("test_group", "1234567890");
        $this->assertInstanceOf("\LightStepBase\Client\ClientTracer", $inst);

        // Is it really a singleton?
        $inst2 = LightStep::getInstance("test_group", "1234567890");
        $this->assertSame($inst, $inst2);
    }
}
