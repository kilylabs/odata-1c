<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 11/8/17
 * Time: 3:16 PM
 */

namespace Kily\Tools1C\OData;


abstract class Profiler
{
    protected $request = null;
    public function setRequest($request) {
        $this->request = $request;
    }

    abstract public function begin();
    abstract public function end();
}