<?php

namespace Hydro\Core;

use Hydro\Hydro;
use Hydro\Core\Parser;
use PDO;

class Builder extends Parser
{
    protected $pdo;
    protected $handler;

    protected $table;
    protected $tableWithoutAlias;

    protected $select = [
        'prefunction' => null,
        'fields' => ['*']
    ];

    protected $insert = [];
    protected $update = [];

    protected $where = [];
    protected $joins = [];
    protected $groupBy = [];
    protected $having = [];
    protected $orderBy = [];
    protected $limit = [];

    protected $params = [];

    protected $rawSql = null;

    public function __construct(?string $table, PDO $pdo, callable $handler)
    {
        $this->pdo = $pdo;
        $this->handler = $handler;

        if (!is_null($table)) {
            $this->table = $this->escapeField($table);
            $tableWithoutAlias = $table;

            if (stripos($table, ' as ') != false) {
                list($table, $alias) = preg_split('/ as /i', $table);
                $tableWithoutAlias = $table;
            }

            $this->tableWithoutAlias = $this->escapeField($table);
        }
    }

    /**
     * Allows to create a raw MySQl query
     *
     * @param string $query
     *
     * @return Builder $this;
     *
     */
    public function raw(string $query)
    {
        $this->rawSql = $query;

        return $this;
    }

    /**
     * Set query parameters
     *
     * @return Builder $this;
     *
     */
    public function params()
    {
        $this->params = func_get_args();

        return $this;
    }

    /**
     * INSERT statement
     *
     * @return Builder $this;
     *
     */
    public function insert(array $data)
    {
        $this->currentClause = self::CLAUSE_INSERT;
        $this->isInsert = true;

        $this->insert = $data;

        return $this;
    }

    /**
     * UPDATE statement
     *
     * @return Builder $this;
     *
     */
    public function update(array $data)
    {
        $this->currentClause = self::CLAUSE_UPDATE;
        $this->isUpdate = true;

        $this->update = $data;

        return $this;
    }

    /**
     * DELETE statement
     *
     * @return Builder $this;
     *
     */
    public function delete()
    {
        $this->currentClause = self::CLAUSE_DELETE;
        $this->isDelete = true;

        return $this;
    }

    /**
     * SELECT Clause
     *
     * @return Builder $this;
     *
     */
    public function select()
    {
        $this->currentClause = self::CLAUSE_SELECT;

        $args = func_get_args();

        if (count($args) == 1) {
            $this->select['fields'] = [$this->escapeField($args[0])];
        } elseif (!empty($args)) {
            $this->select['fields'] = [];

            foreach ($args as $field) {
                $this->select['fields'][] = $this->escapeField($field);
            }
        }

        return $this;
    }

    /**
     * SELECT DISTINCT statement
     *
     * @return Builder $this;
     *
     */
    public function selectDistinct()
    {
        $this->currentClause = self::CLAUSE_SELECT;

        $this->select['prefunction'] = 'DISTINCT';
        $this->select(func_get_args());

        return $this;
    }

    /**
     * INNER JOIN statement
     *
     * @return Builder $this;
     *
     */
    public function innerJoin()
    {
        $this->currentClause = self::CLAUSE_JOIN;

        $this->handleJoin(func_get_args(), 'INNER JOIN');

        return $this;
    }

    /**
     * LEFT JOIN statement
     *
     * @return Builder $this;
     *
     */
    public function leftJoin()
    {
        $this->currentClause = self::CLAUSE_JOIN;

        $this->handleJoin(func_get_args(), 'LEFT JOIN');

        return $this;
    }

    /**
     * Build conditions on joins with AND operator
     *
     * @return Builder $this;
     *
     */
    public function on()
    {
        $this->currentClause = self::CLAUSE_JOIN;

        $this->handleCondition(func_get_args(), 'AND');

        return $this;
    }

    /**
     * Build conditions on joins with OR operator
     *
     * @return Builder $this;
     *
     */
    public function orOn()
    {
        $this->currentClause = self::CLAUSE_JOIN;

        $this->handleCondition(func_get_args(), 'OR');

        return $this;
    }

    /**
     * WHERE Clause
     *
     * @return Builder $this;
     *
     */
    public function where()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();

        if (
            (count($args) == 1 || (count($args) == 2 && is_array($args[1]))) &&
            is_string($args[0])
        ) {
            $this->where = [$args[0]];
            $this->params = array_merge($this->params, $args[1]);
        } else {
            $this->handleCondition(func_get_args(), 'AND');
        }

        return $this;
    }

    public function whereNotNull()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'AND', self::COND_TYPE_NULL_CHECK, 'IS NOT NULL');

        return $this;
    }

    public function whereNull()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'AND', self::COND_TYPE_NULL_CHECK, 'IS NULL');

        return $this;
    }

    public function whereBetween()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'AND', self::COND_TYPE_BETWEEN, 'BETWEEN');

        return $this;
    }

    public function whereNotBetween()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'AND', self::COND_TYPE_BETWEEN, 'NOT BETWEEN');

        return $this;
    }

    public function whereIn()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'AND', self::COND_TYPE_IN, 'IN');

        return $this;
    }

    public function whereNotIn()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'AND', self::COND_TYPE_IN, 'NOT IN');

        return $this;
    }

    public function whereLike()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'AND', self::COND_TYPE_LIKE, 'LIKE');

        return $this;
    }

    public function orWhere()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $this->handleCondition(func_get_args(), 'OR');

        return $this;
    }

    public function orWhereNotNull()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'OR', self::COND_TYPE_NULL_CHECK, 'IS NOT NULL');

        return $this;
    }

    public function orWhereNull()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'OR', self::COND_TYPE_NULL_CHECK, 'IS NULL');

        return $this;
    }

    public function orWhereBetween()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'OR', self::COND_TYPE_BETWEEN, 'BETWEEN');

        return $this;
    }

    public function orWhereNotBetween()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'OR', self::COND_TYPE_BETWEEN, 'NOT BETWEEN');

        return $this;
    }

    public function orWhereIn()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'OR', self::COND_TYPE_IN, 'IN');

        return $this;
    }

    public function orWhereNotIn()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'OR', self::COND_TYPE_IN, 'NOT IN');

        return $this;
    }

    public function orWhereLike()
    {
        $this->currentClause = self::CLAUSE_WHERE;

        $args = func_get_args();
        $this->handleCondition($args, 'OR', self::COND_TYPE_LIKE, 'LIKE');

        return $this;
    }

    /**
     * GROUP BY Clause
     *
     * @param Builder $this
     *
     */
    public function groupBy()
    {
        $this->currentClause = self::CLAUSE_GROUP;

        $args = func_get_args();

        foreach ($args as $field) {
            $this->groupBy[] = $this->escapeField($field);
        }

        return $this;
    }

    /**
     * HAVING Clause
     *
     * @param Builder $this
     *
     */
    public function having()
    {
        $this->currentClause = self::CLAUSE_HAVING;

        $this->handleCondition(func_get_args(), 'AND');

        return $this;
    }

    /**
     * HAVING Clause with OR operator
     *
     * @param Builder $this
     *
     */
    public function orHaving()
    {
        $this->currentClause = self::CLAUSE_HAVING;

        $this->handleCondition(func_get_args(), 'OR');

        return $this;
    }

    /**
     * ORDER BY Clause
     *
     * @param Builder $this
     *
     */
    public function orderBy()
    {
        $this->currentClause = self::CLAUSE_ORDER;

        $args = func_get_args();

        foreach ($args as $field) {
            $this->orderBy[] = $this->escapeField($field);
        }

        return $this;
    }

    /**
     * ORDER BY Clause with DESC sorting
     *
     * @param Builder $this
     *
     */
    public function orderByDesc()
    {
        $this->currentClause = self::CLAUSE_ORDER;

        $args = func_get_args();

        foreach ($args as $field) {
            $this->orderBy[] = $this->escapeField($field) . ' DESC';
        }

        return $this;
    }

    /**
     * LIMIT Clause
     *
     * @param Builder $this
     *
     */

    public function limit(int $limit, int $offset = 0)
    {
        $this->currentClause = self::CLAUSE_LIMIT;

        if (is_int($offset)) {
            $this->limit[] = $offset;
        }

        $this->limit[] = $limit;

        return $this;
    }

    /**
     * Build and fetch multiple records
     *
     * @return $handler
     *
     */
    public function get()
    {
        return ($this->handler)(
            $this->pdo,
            $this->build(),
            $this->params,
            Hydro::QUERY_TYPE_FETCH_MANY
        );
    }

    /**
     * Build and fetch one record
     *
     * @return $handler
     *
     */
    public function one()
    {
        return ($this->handler)(
            $this->pdo,
            $this->build(),
            $this->params,
            Hydro::QUERY_TYPE_FETCH_ONE
        );
    }

    /**
     * Build and fetch multiple records and throw error if record is empty
     *
     * @return $handler
     *
     */
    public function getOrFail()
    {
        $fetch = $this->get();

        if (empty($fetch)) {
            throw new \Error('Record is empty');
        }

        return $fetch;
    }

    /**
     * Build and fetch one record and throw error if record is empty
     *
     * @return $handler
     *
     */
    public function oneOrFail()
    {
        $fetch = $this->one();

        if (empty($fetch)) {
            throw new \Error('Record is empty');
        }

        return $fetch;
    }

    /**
     * Execute a query without fetching
     *
     * @return $handler
     *
     */
    public function execute()
    {
        return ($this->handler)(
            $this->pdo,
            $this->build(),
            $this->params,
            Hydro::QUERY_TYPE_EXECUTE
        );
    }

    /**
     * Build and return the query as string
     *
     * @return $query
     *
     */
    public function sql()
    {
        return $this->build();
    }
}
