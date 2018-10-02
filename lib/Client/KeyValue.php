<?php

namespace LightStepBase\Client;


/**
 * Class KeyValue is a simple key/value pairing.
 * @package LightStepBase\Client
 */
class KeyValue
{
    protected $_key = "";
    protected $_value = "";

    /**
     * KeyValue constructor.
     * @param string $key
     * @param string $value
     */
    public function __construct($key, $value) {
        $this->_key = $key;
        $this->_value = $value;
    }

    /**
     * @return string The key.
     */
    public function getKey() {
        return $this->_key;
    }

    /**
     * @return string The value.
     */
    public function getValue() {
        return $this->_value;
    }

    /**
     * @return \CroutonThrift\KeyValue A Thrift representation of this object.
     */
    public function toThrift()
    {
        return new \CroutonThrift\KeyValue([
            'Key' => $this->_key,
            'Value' => $this->_value,
        ]);
    }

    /**
     * @return \Lightstep\Collector\KeyValue A Proto representation of this object.
     */
    public function toProto() {
        $kv = new \Lightstep\Collector\KeyValue();
        $kv->setKey($this->_key);
        $kv->setStringValue($this->_value);
        return $kv;
    }
}