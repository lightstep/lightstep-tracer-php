<?php
/**
 * Created by PhpStorm.
 * User: sarahhaskins
 * Date: 9/25/18
 * Time: 11:15 AM
 */

namespace LightStepBase\Client;


class ReportRequest
{
    protected $_runtime = null;
    protected $_reportStartTime = 0;
    protected $_now = 0;
    protected $_logRecords = null;
    protected $_spanRecords = null;
    protected $_counters = null;

    public function __construct($runtime, $reportStartTime, $now, $logRecords, $spanRecords, $counters) {
        $this->_runtime = $runtime;
        $this->_reportStartTime = $reportStartTime;
        $this->_now = $now;
        $this->_logRecords = $logRecords;
        $this->_spanRecords = $spanRecords;
        $this->_counters = $counters;
    }

    public function toThrift() {
        // Convert the counters to thrift form
        $thriftCounters = [];
        foreach ($this->_counters as $key => $value) {
            $thriftCounters[] = new \CroutonThrift\NamedCounter([
                'Name' => strval($key),
                'Value' => intval($value),
            ]);
        }

        // Convert the logs to thrift form
        $thriftLogs = [];
        foreach ($this->_logRecords as $lr) {
            $thriftLogs[] = $lr->toThrift();
        }

        // Convert the spans to thrift form
        $thriftSpans = [];
        foreach ($this->_spanRecords as $sr) {
            $thriftSpans[] = $sr->toThrift();
        }

        return new \CroutonThrift\ReportRequest([
            'runtime'         => $this->_runtime->toThrift(),
            'oldest_micros'   => $this->_reportStartTime,
            'youngest_micros' => $this->_now,
            'log_records'     => $thriftLogs,
            'span_records'    => $thriftSpans,
            'counters'        => $thriftCounters,
        ]);
    }

}