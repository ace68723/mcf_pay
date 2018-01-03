<?php

namespace App\Exceptions;

use Exception;

class RttException extends Exception
{
    protected $inner_code;
    protected $context;
    const CHANNEL_NOT_ACTIVATED = 400001;
    const CHANNEL_NOT_SUPPORTED = 400002;
    const ROLE_CHECK_FAIL       = 400003;
    const NOT_FOUND             = 400004;
    const SYSTEM_ERROR          = 400005;
    const QUERY_LATER           = 400006;
    const INVALID_PARAMETER     = 400007;
    const WX_ERROR_VALIDATION   = 400008;
    const WX_ERROR_RETRY        = 400008;
    const WX_ERROR_BIZ          = 400008;
    const WX_ERROR_RAW          = 400008;
    const AL_ERROR_VALIDATION   = 400009;
    const AL_ERROR_RETRY        = 400009;
    const AL_ERROR_BIZ          = 400009;
    const AL_ERROR_RAW          = 400009;
    const NOT_FOUND_REMOTE      = 400010;

    public function __construct(string $const_name, $context = null) {
        parent::__construct($const_name, 1);
        if (defined('self::'.$const_name)) {
            $this->inner_code = constant('self::'.$const_name);
        }
        else {
        }
        $this->context = $context;
    }

    public function getInnerCode() {
        return $this->inner_code;
    }
    public function getContext() {
        return $this->context;
    }
}
