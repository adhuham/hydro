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
        if (is_null(self::$instance)) {
            self::$instance = new static();
            if (method_exists(self::$instance, 'initialize')) {
                self::$instance->initialize();
            }
        }
    }

    public static function query()
    {
        self::createInstance();

        return self::$instance->hydro->model(self::$instance);
    }

    public static function builder()
    {
        self::createInstance();

        return self::$instance->query()->withoutJoins()->withoutFilters();
    }
}
