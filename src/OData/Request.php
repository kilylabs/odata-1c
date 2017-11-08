<?php
/**
 * Created by PhpStorm.
 * User: execut
 * Date: 11/8/17
 * Time: 4:26 PM
 */

namespace Kily\Tools1C\OData;


class Request
{
    protected $url = null;
    protected $options = null;
    protected $method = null;

    public function __construct($url, $options, $method)
    {
        $this->url = $url;
        $this->options = $options;
        $this->method = $method;
    }

    public function getUrl() {
        return $this->url;
    }

    public function getOptions() {
        return $this->options;
    }

    public function getMethod() {
        return $this->method;
    }
}