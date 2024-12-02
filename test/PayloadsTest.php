<?php

class TestClass001 {
    public $a = 7;
    public $b = "b";

    public function method() {
        echo "Hello world!\n";
    }
}

class PayloadsTest extends BaseLightStepTest {

    /**
     * @doesNotPerformAssertions
     */
    public function testDataTypes() {
        $runtime = $this->createTestTracer("test_group", "1234567890");
        $span = $runtime->startSpan('test_span');
        $span->infof("This doesn't have a payload");

        $arr = array('hello' => 'world');
        $ref = &$arr;

        $data = array(
            null, Null, NULL,
            true, True, TRUE,
            false, False, FALSE,
            0, 1, -1, 237894327,
            0.0, 1.0, -1.0, 1.1e42,
            NAN,
            INF,
            "",
            "this is a string",
            "this is\n\0a string?",
            array(),
            array(0, 1, 2),
            array(0 => 1, 2 => 7),
            array(
                'a' => 'b',
                'c' => array('d', 'e'),
                'f' => array('g' => 'h'),
            ),
            $arr,
            $ref,
            new TestClass001(),
            function() { echo "This is an anonymous function!"; },
        );
        foreach ($data as $d) {
            $span->infof("test", $d);
        }

        // "Resources" are a special type in PHP
        // http://php.net/manual/en/language.types.resource.php
        $resource = tmpfile();
        $span->infof("test", $resource);
        $span->finish();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDataTypes2() {
        $runtime = $this->createTestTracer("test_group", "1234567890");
        $span = $runtime->startSpan('test_span');

        $span->infof("This doesn't have a payload");

        $arr = array('hello' => 'world');
        $ref = &$arr;

        $data = array(
            null, Null, NULL,
            true, True, TRUE,
            false, False, FALSE,
            0, 1, -1, 237894327,
            0.0, 1.0, -1.0, 1.1e42,
            NAN,
            INF,
            "",
            "this is a string",
            "this is\n\0a string?",
            array(),
            array(0, 1, 2),
            array(0 => 1, 2 => 7),
            array(
                'a' => 'b',
                'c' => array('d', 'e'),
                'f' => array('g' => 'h'),
            ),
            $arr,
            $ref,
            new TestClass001(),
            function() { echo "This is an anonymous function!"; },
        );
        foreach ($data as $d) {
            $span->logEvent("test", $d);
        }

        // "Resources" are a special type in PHP
        // http://php.net/manual/en/language.types.resource.php
        $resource = tmpfile();
        $span->logEvent("test", $resource);

        $span->finish();
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCircularReferences() {
        $runtime = $this->createTestTracer("test_group", "1234567890");
        $span = $runtime->startSpan('test_span');

        $a = array('next' => null);
        $b = array('next' => &$a);
        $span->infof("test", $a, $b);

        $a['next'] = &$b;
        $span->infof("test", $a, $b);
    }

    protected function _wrapValue($value, $depth) {
        $key = "key_$depth";
        $arr = array($key => $value);
        for ($x = $depth-1; $x >= 0; $x--) {
            $inner = $arr;
            $arr = array("key_$x" => $inner);
        }
        return $arr;
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testDeeplyNested() {
        $runtime = $this->createTestTracer("test_group", "1234567890");
        $span = $runtime->startSpan('test_span');
        $span->infof("test", $this->_wrapValue("value!", 2));
        $span->infof("test", $this->_wrapValue("value!", 4));
        $span->infof("test", $this->_wrapValue("value!", 8));
        $span->infof("test", $this->_wrapValue("value!", 10));
        $span->infof("test", $this->_wrapValue("value!", 100));
        $span->infof("test", $this->_wrapValue("value!", 1000));
        $span->finish();
    }
}
