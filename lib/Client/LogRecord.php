<?php

namespace LightStepBase\Client;

use Lightstep\Collector\Log;
use Google\Protobuf\Timestamp;


/**
 * Class LogRecord Encapsulates the fields of a log message.
 * @package LightStepBase\Client
 */
class LogRecord
{
    protected $_fields = NULL;
    private $_util = NULL;

    /**
     * LogRecord constructor.
     * @param array $fields
     */
    public function __construct($fields) {
        $this->_fields = $fields;
        $this->_util = new Util();
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

    /**
     * @return Log A Proto representation of this object.
     */
    public function toProto() {
        $keyValues = [];
        foreach ($this->_fields as $key => $value) {
            if (!$key || !$value) {
                continue;
            }
            if ($key == 'timestamp_micros') {
                continue;
            }
            $keyValues[] = new \Lightstep\Collector\KeyValue([
                'key' => $key,
                'string_value' => $value,
            ]);
        };

        $logTime = NULL;
        if (array_key_exists('timestamp_micros', $this->_fields)) {
            $logTime = $this->_fields['timestamp_micros'];
        } else {
            $logTime = intval($this->_util->nowMicros());
        }

        return new Log([
            'timestamp' => new Timestamp([
                'seconds' => floor($logTime / 1000000),
                'nanos' => $logTime % 1000000,
            ]),
            'fields' => $keyValues,
        ]);
    }
}