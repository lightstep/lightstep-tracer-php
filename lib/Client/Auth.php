<?php
namespace LightStepBase\Client;

require_once(dirname(__FILE__) . "/Util.php");
require_once(dirname(__FILE__) . "/../../thrift/CroutonThrift/Types.php");

class Auth
{
    protected $_accessToken = "";

    public function __construct($accessToken) {
        $this->_accessToken = $accessToken;
    }

    public function getAccessToken() {
        return $this->_accessToken;
    }

    public function toThrift() {
        return new \CroutonThrift\Auth([
            'access_token' => strval($this->_accessToken),
        ]);
    }
}