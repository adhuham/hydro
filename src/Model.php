<?php

namespace Hydro;

abstract class Model
{
    public $hydro;
    private static $instance = null;

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
        if (is_null(static::$instance)) {
            static::$instance = new static();
            if (method_exists(static::$instance, 'initialize')) {
                static::$instance->initialize();
            }
        }
    }

    public static function query()
    {
        static::createInstance();

        return static::$instance->hydro->model(static::$instance);
    }

    public static function builder()
    {
        static::createInstance();

        return static::$instance->query()->withoutJoins()->withoutFilters();
    }
}
