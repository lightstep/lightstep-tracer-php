<?php
namespace LightStepBase\Client;

require_once(dirname(__FILE__) . "/Util.php");
require_once(dirname(__FILE__) . "/../../thrift/CroutonThrift/Types.php");

/**
 * Class Auth encapsulates the data required to create an Auth object for RPC.
 * @package LightStepBase\Client
 */
class Auth
{
    protected $_accessToken = "";

    /**
     * Auth constructor.
     * @param string $accessToken Identifier for a project, used to authenticate with LightStep satellites.
     */
    public function __construct($accessToken) {
        $this->_accessToken = $accessToken;
    }

    /**
     * @return string The access token.
     */
    public function getAccessToken() {
        return $this->_accessToken;
    }

    /**
     * @return \CroutonThrift\Auth A Thrift representation of this object.
     */
    public function toThrift() {
        return new \CroutonThrift\Auth([
            'access_token' => strval($this->_accessToken),
        ]);
    }
}