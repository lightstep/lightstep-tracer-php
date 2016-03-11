<?php

use LightStepBase\Span;

require_once(__DIR__ . '/vendor/Thrift/Type/TType.php');
require_once(__DIR__ . '/Client/ClientTracer.php');

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
     * @return \LightStepBase\Tracer
     * @throws Exception if the component name or access token is not a valid string
     * @throws Exception if the tracer singleton has already been initialized
     */
    public static function initGlobalTracer($component_name, $access_token, $opts = null) {

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
                $opts = array();
            }
            self::$_singleton->options(array_merge($opts, array(
                'component_name' => $component_name,
                'access_token' => $access_token,
            )));
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
     * @return \LightStepBase\Tracer
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
     * @return \LightStepBase\Tracer
     * @throws Exception if the group name or access token is not a valid string.
     */
    public static function newTracer ($component_name, $access_token, $opts = NULL) {
        if (is_null($opts)) {
            $opts = array();
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
     * @return \LightStepBase\Span
     */
    public static function startSpan($operationName, $fields = NULL) {
        return self::getInstance()->startSpan($operationName, $fields);
    }

    public static function infof($fmt) {
        self::getInstance()->_log('I', $fmt, func_get_args());
    }

    public static function warnf($fmt) {
        self::getInstance()->_log('W', $fmt, func_get_args());
    }

    public static function errorf($fmt) {
        self::getInstance()->_log('E', $fmt, func_get_args());
    }

    public static function fatalf($fmt) {
        self::getInstance()->_log('F', $fmt, func_get_args());
    }

    public static function flush() {
        self::getInstance()->flush();
    }

    public static function disable() {
        self::getInstance()->disable();
    }
};
