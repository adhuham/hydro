<?php

namespace Hydro;

abstract class Model
{
    public $hydro;

    public $join;
    public $filter;

    public function table()
    {
        return $this->hydro->model($this);
    }

    public function query()
    {
        return $this->table();
    }
}
