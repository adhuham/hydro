<?php

namespace Hydro;

abstract class Model
{
    public $hydro;
    private $modelInstance = null;

    public $fields = [];
    public $customFields = [];
    public $join = [];
    public $filter = [];

    public function table()
    {
        if (method_exists($this, 'initialize')) {
            $this->initialize();
        }

        return $this->hydro->model($this);
    }

    public static function query()
    {
        if (is_null(self::$modelInstance)) {
            self::$modelInstance = new static();
            if (method_exists(self::$modelInstance, 'initialize')) {
                self::$modelInstance->initialize();
            }
        }

        return self::$modelInstance->hydro->model(self::$modelInstance);
    }
}
