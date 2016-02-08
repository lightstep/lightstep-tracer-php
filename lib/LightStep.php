<?php

use LightStepBase\ActiveSpan;

require_once(__DIR__ . '/Client/ClientRuntime.php');

class LightStep {

    /**
     * The singleton instance of the runtime.
     */
    private static $_singleton;

    /**
     * Initializes and returns the singleton instance of the Runtime.
     *
     * For convenience, multiple calls to initialize are allowed. For example,
     * in library code with more than possible first entry-point, this may
     * be helpful.
     *
     * @return \LightStepBase\Runtime
     * @throws Exception if the group name or access token is not a valid string
     * @throws Exception if the runtime singleton has already been initialized
     */
    public static function initialize($group_name, $access_token, $opts = null) {

        if (!is_string($group_name) || strlen($group_name) == 0) {
            throw new Exception("Invalid group_name");
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
                'group_name' => $group_name,
                'access_token' => $access_token,
            )));
        } else {
            self::$_singleton = self::newRuntime($group_name, $access_token, $opts);
        }
        return self::$_singleton;
    }

    /**
     * Returns the singleton instance of the Runtime.
     *
     * For convenience, this function can be passed the  $group_name and
     * $access_token parameters to also initialize the runtime singleton. These
     * values will be ignored on any calls after the first to getInstance().
     *
     * @param $group_name Group name to use for the runtime
     * @param $access_token The project access token
     * @return \LightStepBase\Runtime
     * @throws Exception if the group name or access token is not a valid string
     */
    public static function getInstance($group_name = NULL, $access_token = NULL, $opts = NULL) {
        if (!isset(self::$_singleton)) {
            self::$_singleton = self::newRuntime($group_name, $access_token, $opts);
        }
        return self::$_singleton;
    }


    /**
     * Creates a new runtime instance.
     *
     * @param $group_name Group name to use for the runtime
     * @param $access_token The project access token
     * @return \LightStepBase\Runtime
     * @throws Exception if the group name or access token is not a valid string.
     */
    public static function newRuntime ($group_name, $access_token, $opts = NULL) {
        if (is_null($opts)) {
            $opts = array();
        }

        // It is valid to create and use the runtime before it is fully configured.
        // The only constraint is that it will not be able to flush data until the
        // configuration is complete.
        if ($group_name != NULL) {
            $opts['group_name'] = $group_name;
        }
        if ($group_name != NULL) {
           $opts['access_token'] = $access_token;
        }
        return new LightStepBase\Client\ClientRuntime($opts);
    }

    /*
     * Runtime API
     */

    /**
     * @return \LightStepBase\ActiveSpan
     */
    public static function startSpan() {
        return self::getInstance()->startSpan();
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
