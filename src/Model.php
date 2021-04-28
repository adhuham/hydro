<?php

namespace Hydro;

abstract class Model
{
    public $hydro;
    private static $instance = [];

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

    private static function createInstance()
    {
        $calledClass = static::class;

        if (is_null(self::$instance[$calledClass])) {
            self::$instance[$calledClass] = new static();
            if (method_exists(self::$instance[$calledClass], 'initialize')) {
                self::$instance[$calledClass]->initialize();
            }
        }
    }

    public static function query()
    {
        self::createInstance();

        return self::$instance[static::class]->hydro->model(self::$instance);
    }

    public static function builder()
    {
        self::createInstance();

        return self::$instance[static::class]->query()->withoutJoins()->withoutFilters();
    }
}
