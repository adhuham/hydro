<?php

namespace Hydro;

use Hydro\Core\Builder;
use Hydro\Core\ModelBuilder;
use Hydro\Model;
use PDO;

class Hydro
{
    private $pdo;
    private $handler;

    /**
     * Query Types
     */
    public const QUERY_TYPE_EXECUTE = 1;
    public const QUERY_TYPE_FETCH_MANY = 2;
    public const QUERY_TYPE_FETCH_ONE = 3;

    public function __construct(PDO $pdo, ?callable $handler = null)
    {
        $this->pdo = $pdo;

        if (is_callable($handler)) {
            $this->handler = $handler;
        } else {
            $this->handler = function ($pdo, $query, $params, $mode) {
                return $this->handler($pdo, $query, $params, $mode);
            };
        }
    }

    /**
     * Set table name
     *
     * @param string $table
     *
     * @return Builder
     *
     */
    public function table(string $table)
    {
        return new Builder($table, $this->pdo, $this->handler);
    }

    /**
     * Handles Raw Query
     *
     * @param string $query
     *
     * @return Builder
     *
     */
    public function raw(string $query)
    {
        $builder = new Builder(null, $this->pdo, $this->handler);
        return $builder->raw($query);
    }

    /**
     * Build query from Model instance
     *
     * @param Hydro\Model $model
     *
     * @return Builder
     *
     */
    public function model(Model $model)
    {
        return new ModelBuilder($model, $this->pdo, $this->handler);
    }

    /**
     * Get last insert id
     *
     * @return $id
     *
     */
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * Built-in query handler
     *
     * @param PDO $pdo
     * @param string $query
     * @param array $params
     * @param integer $type
     *
     * @return $data
     *
     */
    private function handler($pdo, $query, $params, $type)
    {
        $query = $pdo->prepare($query);
        if (!empty($params)) {
            foreach ($params as $i => $value) {
                $query->bindParam($i + 1, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
        }
        $execute = $query->execute($params);

        if ($type == self::QUERY_TYPE_EXECUTE) {
            return $execute;
        }

        if ($type == self::QUERY_TYPE_FETCH_MANY) {
            return $query->fetchAll(PDO::FETCH_ASSOC);
        } elseif ($type == self::QUERY_TYPE_FETCH_ONE) {
            return $query->fetch(PDO::FETCH_ASSOC);
        }
    }
}
