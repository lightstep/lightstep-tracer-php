<?php

use PHPUnit\Framework\TestCase;

/**
 * Class BaseLightStepTest
 * @author Josh Wickham
 * @copyright &copy; 2016 Life360, Inc.
 * @since 6/10/16
 */
abstract class BaseLightStepTest extends TestCase
{
    protected function createTestTracer($component_name, $access_token) {
        $opts = [
            "debug_disable_flush" => TRUE
        ];

        return LightStep::newTracer("test_group", "1234567890", $opts);
	}

    /**
     * Helper to grab protected fields -- useful for the unit tests!
     *
     * @param object $obj an object with a field $field which may be protected/private
     * @param string $field the field to read
     * @return mixed
     */
    protected function peek($obj, $field) {
        $reflection = new ReflectionClass($obj);
        $property = $reflection->getProperty($field);
        $property->setAccessible(true);
        return $property->getValue($obj);
    }

}
