<?php

namespace Hydro\Core;

class Parser
{
    // tracks the current clause
    protected $currentClause = 1;

    /**
     * MySQL Query Parts
     */
    protected const CLAUSE_SELECT = 1;
    protected const CLAUSE_FROM = 2;
    protected const CLAUSE_JOIN = 3;
    protected const CLAUSE_WHERE = 4;
    protected const CLAUSE_GROUP = 5;
    protected const CLAUSE_HAVING = 6;
    protected const CLAUSE_ORDER = 7;
    protected const CLAUSE_LIMIT = 8;

    protected const CLAUSE_INSERT = 9;
    protected const CLAUSE_UPDATE = 10;

    /**
     * Condition Types
     */
    protected const COND_TYPE_NORMAL = 1;
    protected const COND_TYPE_NULL_CHECK = 2;
    protected const COND_TYPE_BETWEEN = 3;
    protected const COND_TYPE_IN = 4;
    protected const COND_TYPE_LIKE = 5;

    // temporary location to store conditions of JOIN statement
    private $joinCond = [];

    // tracks the begining of nested condition
    private $isNestedCondStart = false;

    protected $isInsert = false;
    protected $isUpdate = false;

    /**
     * Builds conditional statements on both WHERE clause and JOIN clause
     *
     * @param array $args
     * @param string $operator
     * @param int $type
     * @param string $comparison
     *
     * @return void
     *
     */
    protected function handleCondition(
        array $args,
        string $operator,
        int $type = self::COND_TYPE_NORMAL,
        string $comparison = '='
    ) {
        if ($this->currentClause == self::CLAUSE_JOIN) {
            $target = &$this->joinCond;
        } elseif ($this->currentClause == self::CLAUSE_HAVING) {
            $target = &$this->having;
        } else {
            $target = &$this->where;
        }

        $target = &$this->where;

        if (count($target) > 0 && !$this->isNestedCondStart) {
            $target[] = $operator;
        }

        $this->isNestedCondStart = false;

        // handle nested conditions
        if (count($args) == 1 && is_callable($args[0])) {
            $target[] = '(';
            $this->isNestedCondStart = true;
            $args[0]($this);
            $target[] = ')';

            return $this;
        }

        $field = $args[0] ?? null;
        $field = $this->escapeField($field);

        $placeholder = null;
        $hasParam = false;

        if ($type == self::COND_TYPE_NORMAL) {
            if (count($args) == 2) {
                $param = $args[1];
            } else {
                $comparison = $args[1];
                $param = $args[2];
            }
            $hasParam = true;
        } elseif ($type == self::COND_TYPE_BETWEEN) {
            $param = $args[1];
            $hasParam = true;
            $placeholder = '? AND ?';
        } elseif ($type == self::COND_TYPE_IN) {
            $param = $args[1];
            $hasParam = true;
            $placeholder = '(' . rtrim(str_repeat('?, ', count($param)), ', ') . ')';
        } elseif ($type == self::COND_TYPE_LIKE) {
            $param = $args[1];
            $hasParam = true;
        }

        if ($hasParam) {
            if (is_array($param)) {
                // handles more than one params passed as array
                $this->params = array_merge($this->params, $param);
            } else {
                $this->params[] = $param;
            }
        }

        // set the placeholder if it isn't set already and if a parameter exists
        if (!isset($placeholder) && $hasParam) {
            $placeholder = '?';
        }

        if (in_array($this->currentClause, [self::CLAUSE_JOIN, self::CLAUSE_HAVING])) {
            $target[] = $field . ' ' . $comparison . ' ' . $param;
        } else {
            $target[] = $field . ' ' . $comparison . ' ' . $placeholder;
        }
    }

    /**
     * Builds JOIN clause
     *
     * @param array $args
     * @param string $joinType
     *
     * @return void
     *
     */
    protected function handleJoin(array $args, string $joinType = 'INNER JOIN')
    {
        $field = $this->escapeField($args[0]);

        $this->join[] = $joinType;
        $this->join[] = $field;
        $this->join[] = 'ON (';

        // handle nested ON Clause
        if (isset($args[1]) && is_callable($args[1])) {
            // nested callback
            $args[1]($this);

            $this->join[] = implode(' ', $this->joinCond);
            $this->join[] = ')';

            // reset
            $this->joinCond = [];

            return $this;
        }

        $tbl1 = $this->escapeField($args[1]);
        $tbl2 = $this->escapeField($args[3]);
        $comparison = $args[2];

        $this->join[] = $tbl1 . ' ' . $comparison . ' ' . $tbl2;
        $this->join[] = ')';
    }

    /**
     * Put backticks in table field
     *
     * @param string $field
     *
     * @return string $field
     */
    protected function escapeField(string $field)
    {
        $field = trim($field);

        // do not backtick if a MySQL Function is called
        if (stripos($field, '(') !== false && stripos($field, ')') !== false) {
            return $field;
        }

        // do not backtick if a comma ', ' exists in the field
        if (stripos($field, ',') !== false) {
            return $field;
        }

        // do not backtick if a backtick '`' exists in the field
        if (stripos($field, '`') !== false) {
            return $field;
        }

        // split the field and alias if the field is aliased
        if (stripos($field, ' as ') != false) {
            list($field, $alias) = preg_split('/ as /i', $field);
        }

        if (stripos($field, '.') === false) {
            $field = '`' . $field . '`';
        } else {
            list($table, $field) = explode('.', $field);
            $field = '`' . $table . '`.`' . $field . '`';
        }

        if (isset($alias)) {
            $field = $field . ' as ' . $alias;
        }

        return $field;
    }

    /**
     * Build the SQL statement by joining all the query parts
     *
     * @return string $query
     *
     */
    protected function build()
    {
        if (!is_null($this->rawSql)) {
            return $this->rawSql;
        }

        $query = null;

        if ($this->isInsert) {
            $fields = [];
            foreach (array_keys($this->insert) as $field) {
                $fields[] = $this->escapeField($field);
            }

            $values = array_values($this->insert);
            foreach ($values as $value) {
                $this->params[] = trim($value) != '' ? $value : null;
            }

            $query .= 'INSERT INTO ' . $this->tableWithoutAlias . ' ';
            $query .= '(' . implode(', ', $fields) . ')';
            $query .= ' VALUES ';
            $query .= '(' . implode(', ', array_fill(1, count($values), '?')) . ')';

            return $query;
        }

        if ($this->isUpdate) {
            $fields = [];
            foreach (array_keys($this->update) as $field) {
                $fields[] = $this->escapeField($field) . ' = ?';
            }

            $values = array_values($this->update);

            $params = [];
            foreach ($values as $value) {
                $params[] = trim($value) != '' ? $value : null;
            }

            $this->params = array_merge($params, $this->params);

            $query .= 'UPDATE ' . $this->table;
            $query .= ' SET ' . implode(', ', $fields);
            if (!empty($this->where)) {
                $query .= ' WHERE ' . implode(' ', $this->where);
            }

            return $query;
        }

        $query .= 'SELECT ';
        $query .= trim($this->select['prefunction'] . ' ');
        $query .= implode(', ', $this->select['fields']);

        $query .= ' FROM ' . $this->table;

        if (!empty($this->join)) {
            $query .= ' ' . implode(' ', $this->join);
        }

        if (!empty($this->where)) {
            $query .= ' WHERE ' . implode(' ', $this->where);
        }

        if (!empty($this->groupBy)) {
            $query .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        if (!empty($this->having)) {
            $query .= ' HAVING ' . implode(' ', $this->having);
        }

        if (!empty($this->orderBy)) {
            $query .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        if (!empty($this->limit)) {
            $query .= ' LIMIT ' . implode(', ', $this->limit);
        }

        return $query;
    }
}
