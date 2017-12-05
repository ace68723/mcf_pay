<?php

namespace App\Exceptions;

use Exception;

class RttException extends Exception
{
    protected $inner_code;
    protected $context;
    const CHANNEL_NOT_ACTIVATED = 400001;
    const CHANNEL_NOT_SUPPORTED = 400002;
    const SYSTEM_ERROR          = 400003;
    const ROLE_CHECK_FAIL       = 400003;
    const NOT_FOUND             = 400003;
    const QUERY_LATER           = 400003;
    const INVALID_PARAMETER     = 400003;
    const WX_ERROR_VALIDATION   = 400004;
    const WX_ERROR_RETRY        = 400004;
    const WX_ERROR_BIZ          = 400004;
    const WX_ERROR_RAW          = 400004;
    const AL_ERROR_VALIDATION   = 400004;
    const AL_ERROR_RETRY        = 400004;
    const AL_ERROR_BIZ          = 400004;
    const AL_ERROR_RAW          = 400004;

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
