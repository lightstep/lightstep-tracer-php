<?php

// Abbrev. to grab protected fields -- useful for the unit tests!
function peek($obj, $field) {
    return PHPUnit_Framework_Assert::readAttribute($obj, $field);
}

class LightStepTest extends PHPUnit_Framework_TestCase {

    public function testGetInstance() {
        $inst = LightStep::getInstance("test_group", "1234567890");
        $this->assertInstanceOf("\LightStepBase\Client\ClientRuntime", $inst);

        // Is it really a singleton?
        $inst2 = LightStep::getInstance("test_group", "1234567890");
        $this->assertSame($inst, $inst2);
    }
}
