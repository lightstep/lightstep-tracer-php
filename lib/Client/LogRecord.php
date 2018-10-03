<?php

namespace LightStepBase\Client;


/**
 * Class LogRecord Encapsulates the fields of a log message.
 * @package LightStepBase\Client
 */
class LogRecord
{
    protected $_fields = null;

    /**
     * LogRecord constructor.
     * @param array $fields
     */
    public function __construct($fields) {
        $this->_fields = $fields;
    }

    /**
     * @return \CroutonThrift\LogRecord A Thrift representation of this object.
     */
    public function toThrift() {
        return new \CroutonThrift\LogRecord($this->_fields);
    }
}