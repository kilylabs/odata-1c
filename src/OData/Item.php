<?php

namespace Kily\Tools1C\OData;

class Item 
{
    protected $id;

    public function __construct($id) {
        $this->id = $id;
    }

    public function __toString() {
        return $this->id;
    }
}
