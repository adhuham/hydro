<?php

namespace Hydro;

abstract class Model
{
    protected $hydro;
    private static $modelInstance;

    public $join;
    public $filter;

    public function table()
    {
        return $this->hydro->model($this);
    }

    public static function query()
    {
        if (is_null(self::$modelInstance)) {
            self::$modelInstance = new static();
        }

        return self::$modelInstance->table();
    }
}
