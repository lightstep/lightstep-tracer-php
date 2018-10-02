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
    protected $_fields = null;
    private $_util = null;

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
        $logTime = NULL;
        if (array_key_exists('timestamp_micros', $this->_fields)) {
            $logTime = $this->_fields['timestamp_micros'];
        } else {
            $logTime = intval($this->_util->nowMicros());
        }

        $protoTime = new Timestamp();
        $protoTime->setSeconds(floor($logTime / 1000000));
        $protoTime->setNanos($logTime % 1000000);

        $protoLog = new Log();
        $protoLog->setTimestamp($protoTime);

        $keyValues = [];
        foreach ($this->_fields as $key => $value) {
            if (!$key || !$value) {
                continue;
            }
            if ($key == 'timestamp_micros') {
                continue;
            }
            $keyValue = new \Lightstep\Collector\KeyValue();
            $keyValue->setKey($key);
            $keyValue->setStringValue($value);
            $keyValues[] = $keyValue;
        };

        $protoLog->setFields($keyValues);

        return $protoLog;
    }
}