<?php
namespace LightStepBase\Client;

class Util {

    protected $_rng = null;

    public function __construct() {
        $seed = floor(microtime(TRUE) * 1000.0 * 1000.0);
        $this->_rng = new \mersenne_twister\twister($seed);
    }

    /**
     * Returns an integer in the following closed range (i.e. inclusive,
     * $lower <= $x <= $upper).
     */
    public function randIntRange($lower, $upper) {
        return $this->_rng->rangeint($lower, $upper);
    }

    /**
     * Returns a positive or *negative* 32-bit integer.
     * http://kingfisher.nfshost.com/sw/twister/
     */
    public function randInt32() {
        return $this->_rng->int32();
    }

    public static function nowMicros() {
        // Note: microtime returns the current time *in seconds* but with
        // microsecond accuracy (not the current time in microseconds!).
        return floor(microtime(TRUE) * 1000.0 * 1000.0);
    }

    public function pushWithMax(&$arr, $item, $max) {
        if (!($max > 0)) {
            $max = 1;
        }

        $arr[] =  $item;

        // Simplistic random discard
        $count = count($arr);
        if ($count > $max) {
            $i = $this->randIntRange(0, $max - 1);
            $arr[$i] = array_pop($arr);
            return true;
        } else {
            return false;
        }
    }
}
