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
        $ts = 0;
        $fields = [];
        foreach ($this->_fields as $key => $value) {
            if ($key == 'timestamp_micros') {
                $ts = $value;
                continue;
            }
            $fields[] = new \CroutonThrift\KeyValue([
                'Key' => $key,
                'Value' => $value,
            ]);
        }

        return new \CroutonThrift\LogRecord([
            'fields' => $fields,
            'timestamp_micros' => $ts
        ]);
    }
}