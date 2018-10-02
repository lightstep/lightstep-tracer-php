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

    /**
     * Generates a random ID (not a RFC-4122 UUID).
     */
    public function _generateUUIDString() {
        // must return less than 7fffffffffffffff

        return sprintf("%08x%08x",
            $this->randInt32(),
            $this->randInt32()
        );
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
    public static function pushIfSpaceAllows(&$arr, $item, $max) {
        if ($max <= 0) {
            $max = 1;
        }

        if (count($arr) >= $max) {
            return false;
        }

        $arr[] =  $item;
        return true;
    }

    public static function hexdec($input) {
        $str_high = substr($input, 0, 8);
        $str_low = substr($input, 8, 8);

        $dec_high = hexdec($str_high);
        $dec_low  = hexdec($str_low);

        //workaround for argument 0x100000000
        $temp = bcmul($dec_high, 0xffffffff);
        $temp2 = bcadd($temp, $dec_high);

        $result = bcadd($temp2, $dec_low);

        return $result;
    }

    public static function dechex($dec) {
        $hex = '';
        do {
            $last = bcmod($dec, 16);
            $hex = dechex($last).$hex;
            $dec = bcdiv(bcsub($dec, $last), 16);
        } while($dec>0);
        return str_pad($hex, 16, "0", STR_PAD_LEFT);
    }
}
