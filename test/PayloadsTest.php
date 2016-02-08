<?php

class TestClass001 {
    public $a = 7;
    public $b = "b";

    public function method() {
        echo "Hello world!\n";
    }
}

class PayloadsTest extends PHPUnit_Framework_TestCase {

    public function testDataTypes() {
        $runtime = LightStep::newRuntime("test_group", "1234567890");

        $runtime->infof("This doesn't have a payload");

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
            $runtime->infof("test", $d);
        }

        // "Resources" are a special type in PHP
        // http://php.net/manual/en/language.types.resource.php
        $resource = tmpfile();
        $runtime->infof("test", $resource);
    }

    public function testCircularReferences() {
        $runtime = LightStep::newRuntime("test_group", "1234567890");

        $a = array('next' => null);
        $b = array('next' => &$a);
        $runtime->infof("test", $a, $b);

        $a['next'] = &$b;
        $runtime->infof("test", $a, $b);
    }

    protected function _wrapValue($value, $depth) {
        $key = "key_$depth";
        if ($depth > 0) {
            return $this->_wrapValue(array($key => $value), $depth - 1);
        } else {
            return $value;
        }
    }

    public function testDeeplyNested() {
        $runtime = LightStep::newRuntime("test_group", "1234567890");
        $runtime->infof("test", $this->_wrapValue("value!", 2));
        $runtime->infof("test", $this->_wrapValue("value!", 4));
        $runtime->infof("test", $this->_wrapValue("value!", 8));
        $runtime->infof("test", $this->_wrapValue("value!", 10));
        $runtime->infof("test", $this->_wrapValue("value!", 100));
        $runtime->infof("test", $this->_wrapValue("value!", 1000));
    }
}
