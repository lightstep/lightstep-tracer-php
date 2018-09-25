<?php
/**
 * Created by PhpStorm.
 * User: sarahhaskins
 * Date: 9/25/18
 * Time: 11:07 AM
 */

namespace LightStepBase\Client;


class KeyValue
{
    protected $_key = "";
    protected $_value = "";

    public function __construct($key, $value) {
        $this->_key = $key;
        $this->_value = $value;
    }

    public function getKey() {
        return $this->_key;
    }

    public function getValue() {
        return $this->_value;
    }

    public function toThrift()
    {
        return new \CroutonThrift\KeyValue([
            'Key' => $this->_key,
            'Value' => $this->_value,
        ]);
    }
}