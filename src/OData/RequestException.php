<?php

namespace Kily\Tools1C\OData;

use Throwable;

class RequestException extends Exception {
    public $request = null;
    public function __construct(Request $request, $message = "", $code = 0, Throwable $previous = null)
    {
        $this->request = $request;
        parent::__construct($message, $code, $previous);
    }
}
