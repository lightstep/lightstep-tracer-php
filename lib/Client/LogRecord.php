<?php
/**
 * Created by PhpStorm.
 * User: sarahhaskins
 * Date: 9/25/18
 * Time: 11:27 AM
 */

namespace LightStepBase\Client;


class LogRecord
{
    protected $_fields = null;

    public function __construct($fields) {
        $this->_fields = $fields;
    }

    public function toThrift() {
        return new \CroutonThrift\LogRecord($this->_fields);
    }
}