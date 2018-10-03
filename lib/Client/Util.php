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

    /**
     * Add an item to the array, ensuring that the size of the array never exceed the max. If adding the item would
     * cause the array count to exceed max, then the item is not added.
     *
     * @param array $arr Array to add item to
     * @param object $item Item to be added
     * @param int $max Max number of items allowed in array, if provided max is <= 0, 1 will be used as the max
     * @return bool True if the item was successfully added to the array
     */
    public function pushIfSpaceAllows(&$arr, $item, $max) {
        if ($max <= 0) {
            $max = 1;
        }

        if (count($arr) >= $max) {
            return false;
        }

        $arr[] =  $item;
        return true;
    }
}
