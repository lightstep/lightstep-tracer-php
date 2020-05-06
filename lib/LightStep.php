<?php

//use LightStepBase\Span;
use OpenTracing\Span;

require_once(__DIR__ . '/vendor/Thrift/Type/TType.php');
require_once(__DIR__ . '/Client/ClientTracer.php');
require_once(__DIR__ . '/generated/Lightstep/Collector/Auth.php');
require_once(__DIR__ . '/generated/Lightstep/Collector/KeyValue.php');
require_once(__DIR__ . '/generated/Lightstep/Collector/Log.php');
require_once(__DIR__ . '/generated/Lightstep/Collector/MetricsSample.php');
require_once(__DIR__ . '/generated/Lightstep/Collector/InternalMetrics.php');
require_once(__DIR__ . '/generated/Lightstep/Collector/Reference.php');
require_once(__DIR__ . '/generated/Lightstep/Collector/Reference/Relationship.php');
require_once(__DIR__ . '/generated/Lightstep/Collector/ReportRequest.php');
require_once(__DIR__ . '/generated/Lightstep/Collector/Reporter.php');
require_once(__DIR__ . '/generated/Lightstep/Collector/SpanContext.php');
require_once(__DIR__ . '/generated/Lightstep/Collector/Span.php');
require_once(__DIR__ . '/generated/GPBMetadata/Collector.php');
require_once(__DIR__ . '/generated/GPBMetadata/Google/Api/Annotations.php');
require_once(__DIR__ . '/generated/GPBMetadata/Google/Api/Http.php');

class LightStep {

    /**
     * The singleton instance of the tracer.
     */
    private static $_singleton;

    /**
     * Initializes and returns the singleton instance of the Tracer.
     *
     * For convenience, multiple calls to initialize are allowed. For example,
     * in library code with more than possible first entry-point, this may
     * be helpful.
     *
     * @return \OpenTracing\Tracer
     * @throws Exception if the component name or access token is not a valid string
     * @throws Exception if the tracer singleton has already been initialized
     */
    public static function initGlobalTracer($component_name, $access_token, $opts = NULL) {

        if (!is_string($component_name) || strlen($component_name) == 0) {
            throw new Exception("Invalid component_name");
        }
        if (!is_string($access_token) || strlen($access_token) == 0) {
            throw new Exception("Invalid access_token");
        }

        // If the singleton has already been created, treat the initialization
        // as an options() call instead.
        if (isset(self::$_singleton)) {
            if (!isset($opts)) {
                $opts = [];
            }
            self::$_singleton->options(array_merge($opts, [
                'component_name' => $component_name,
                'access_token' => $access_token,
            ]));
        } else {
            self::$_singleton = self::newTracer($component_name, $access_token, $opts);
        }
        return self::$_singleton;
    }

    /**
     * Returns the singleton instance of the Tracer.
     *
     * For convenience, this function can be passed the $component_name and
     * $access_token parameters to also initialize the tracer singleton. These
     * values will be ignored on any calls after the first to getInstance().
     *
     * @param $component_name Component name to use for the tracer
     * @param $access_token The project access token
     * @return \OpenTracing\Tracer
     * @throws Exception if the group name or access token is not a valid string
     */
    public static function getInstance($component_name = NULL, $access_token = NULL, $opts = NULL) {
        if (!isset(self::$_singleton)) {
            self::$_singleton = self::newTracer($component_name, $access_token, $opts);
        }
        return self::$_singleton;
    }


    /**
     * Creates a new tracer instance.
     *
     * @param $component_name Component name to use for the tracer
     * @param $access_token The project access token
     * @return \OpenTracing\Tracer
     * @throws Exception if the group name or access token is not a valid string.
     */
    public static function newTracer ($component_name, $access_token, $opts = NULL) {
        if (is_null($opts)) {
            $opts = [];
        }

        // It is valid to create and use the tracer before it is fully configured.
        // The only constraint is that it will not be able to flush data until the
        // configuration is complete.
        if ($component_name != NULL) {
            $opts['component_name'] = $component_name;
        }
        if ($access_token != NULL) {
           $opts['access_token'] = $access_token;
        }
        return new LightStepBase\Client\ClientTracer($opts);
    }

    /*
     * Tracer API
     */

    /**
     * @return \OpenTracing\Span
     */
    public static function startSpan($operationName, $fields = NULL) {
        return self::getInstance()->startSpan($operationName, $fields);
    }

    public static function flush() {
        self::getInstance()->flush();
    }

    public static function disable() {
        self::getInstance()->disable();
    }
};
