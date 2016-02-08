<?php
namespace LightStepBase\Client;

use \mersenne_twister\twister;

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

    public function nowMicros() {
        // Note: microtime returns the current time *in seconds* but with
        // microsecond accuracy (not the current time in microseconds!).
        return floor(microtime(TRUE) * 1000.0 * 1000.0);
    }
}
