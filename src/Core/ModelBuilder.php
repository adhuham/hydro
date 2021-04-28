<?php

namespace Hydro\Core;

use Hydro\Core\Builder;
use Hydro\Model;
use PDO;

class ModelBuilder extends Builder
{
    private $model;
    private $alias;

    private $isSelectFieldsGiven = true;
    private $joinModelInstances = [];

    private $modelSelect = [];
    private $modelCustomFields = [];
    private $modelJoins = [];
    private $modelFilters = [];

    private $joinsList = [];

    public function __construct(Model $model, PDO $pdo, callable $handler)
    {
        $this->model = $model;
        $this->alias = $model->alias ?? $model->table;

        $table = $this->model->table . ' as ' . $this->alias;

        parent::__construct($table, $pdo, $handler);

        $this->modelCustomFields = $this->model->customFields;
        $this->modelJoins = $this->model->join;
        $this->modelFilters = $this->model->filter;

        // select all fields in current model by default
        // it will be reset if ->select(...) is called explicitly
        $this->select();
    }

    /**
     * Defines joins which should be included in the final query
     *
     * @return ModelBuilder $this
     *
     */
    public function withJoins()
    {
        $args = func_get_args();

        $this->modelJoins = [];
        foreach ($args as $name) {
            $this->modelJoins[$name] = $this->model->join[$name];
        }

        return $this;
    }

    /**
     * Defines joins that should be excluded in the final query
     *
     * @return ModelBuilder $this
     *
     */
    public function withoutJoins()
    {
        $args = func_get_args();

        if (empty($args)) {
            $this->modelJoins = [];
        } else {
            foreach ($args as $name) {
                unset($this->modelJoins[$name]);
            }
        }

        return $this;
    }

    /**
     * Defines filters which should be included in the final query
     *
     * @return ModelBuilder $this
     *
     */
    public function withFilters()
    {
        $args = func_get_args();

        $this->modelFilters = [];
        foreach ($args as $name) {
            $this->modelFilters[$name] = $this->model->filter[$name];
        }

        return $this;
    }

    /**
     * Defines filters that should be excluded in the final query
     *
     * @return ModelBuilder $this
     *
     */
    public function withoutFilters()
    {
        $args = func_get_args();

        if (empty($args)) {
            $this->modelFilters = [];
        } else {
            foreach ($args as $name) {
                unset($this->modelFilters[$name]);
            }
        }

        return $this;
    }

    /**
     * SELECT Clause
     *
     * @return ModelBuilder $this;
     *
     */
    public function select()
    {
        $this->currentClause = self::CLAUSE_SELECT;

        $args = func_get_args();

        $this->modelSelect = [];

        if (empty($args)) {
            // if no fields given, select all fields in the current model by default
            foreach ($this->model->fields as $field) {
                $this->modelSelect[$this->alias][] = $this->aliasedField(
                    $this->alias,
                    $field
                );
            }
        // handle array-based selection
        } elseif (count($args) == 1 && is_array($args[0])) {
            $this->isSelectFieldsGiven = true;

            foreach ($args[0] as $alias => $fields) {
                $model = $this->joinModelInstances[$alias] ?? null;

                if (is_null($model)) {
                    continue;
                }

                // select all fields (including custom fields)
                if (is_string($fields) && $fields == '*') {
                    foreach ($model->fields as $field) {
                        $this->modelSelect[$alias][] = $this->aliasedField($alias, $field);
                    }

                    foreach ($model->customFields as $field) {
                        $this->modelSelect[$alias][] = $this->aliasedCustomField(
                            $alias,
                            $field,
                            $model->customFields[$field]
                        );
                    }

                    continue;
                }

                // handle single fields passed as string
                if (is_string($fields)) {
                    if (isset($model->customFields[$fields])) {
                        $this->modelSelect[$alias][] = $this->aliasedCustomField(
                            $alias,
                            $fields,
                            $model->customFields[$fields]
                        );
                    } else {
                        $this->modelSelect[$alias][] = $this->aliasedField($alias, $fields);
                    }

                    continue;
                }

                foreach ($fields as $field) {
                    if (isset($model->customFields[$field])) {
                        $this->modelSelect[$alias][] = $this->aliasedCustomField(
                            $alias,
                            $field,
                            $model->customFields[$field]
                        );
                    } else {
                        $this->modelSelect[$alias][] = $this->aliasedField($alias, $field);
                    }
                }
            }
        } else {
            $this->isSelectFieldsGiven = true;

            foreach ($args as $field) {
                // extract aliased prefix and the field name
                $split = explode('.', $field);

                $alias = count($split) == 2 ? $split[0] : $this->alias;
                $realField = $split[1] ?? $split[0];

                if ($this->alias == $alias) {
                    $model = $this->model;
                } elseif (isset($this->joinModelInstances[$alias])) {
                    $model = $this->joinModelInstances[$alias];
                }

                // check if the field exists in the model or any of the joined models
                if (isset($model) && in_array($realField, $model->fields)) {
                    $this->modelSelect[$alias][] = $this->aliasedField(
                        $alias,
                        $realField
                    );
                } elseif (isset($model) && isset($model->customFields[$realField])) {
                    // check if the field is a custom field
                    $this->modelSelect[$alias][] = $this->aliasedCustomField(
                        $alias,
                        $realField,
                        $model->customFields[$realField]
                    );
                } else {
                    $this->modelSelect[$this->alias][] = $field;
                }
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
     * LEFT JOIN statement
     *
     * @return ModelBuilder $this;
     *
     */
    public function leftJoin()
    {
        $this->currentClause = self::CLAUSE_JOIN;

        $this->joinsList[] = $this->resolveJoins('leftJoin', func_get_args());

        return $this;
    }

    /**
     * INNER JOIN statement
     *
     * @return ModelBuilder $this;
     *
     */
    public function innerJoin()
    {
        $this->currentClause = self::CLAUSE_JOIN;

        $this->joinsList[] = $this->resolveJoins('innerJoin', func_get_args());

        return $this;
    }

    /**
     * RIGHT JOIN statement
     *
     * @return ModelBuilder $this;
     *
     */
    public function rightJoin()
    {
        $this->currentClause = self::CLAUSE_JOIN;

        $this->joinsList[] = $this->resolveJoins('rightJoin', func_get_args());

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
        $this->buildJoins();
        $this->buildFilters();
        $this->buildSelect();

        return parent::get();
    }

    /**
     * Build and fetch one record
     *
     * @return $handler
     *
     */
    public function one()
    {
        $this->buildJoins();
        $this->buildFilters();
        $this->buildSelect();

        return parent::one();
    }

    /**
     * Build and return the query as string
     *
     * @return $query
     *
     */
    public function sql()
    {
        $this->buildJoins();
        $this->buildFilters();
        $this->buildSelect();

        return parent::sql();
    }

    /**
     * Build joins
     *
     * @return void
     *
     */
    private function buildJoins()
    {
        // build joins from the model
        if (!empty($this->modelJoins)) {
            foreach ($this->modelJoins as $join) {
                $join($this);
            }
        }

        if (!empty($this->joinsList)) {
            foreach ($this->joinsList as $args) {
                parent::{$args[0]}(...$args[1]);
            }
        }
    }

    /**
     * Build filters defined in the model
     *
     * @return void
     *
     */
    private function buildFilters()
    {
        if (!empty($this->modelFilters)) {
            foreach ($this->modelFilters as $filter) {
                $filter($this);
            }
        }
    }

    /**
     * Build the select statement
     *
     * @return void
     *
     */
    private function buildSelect()
    {
        $this->select['fields'] = [];

        if (!empty($this->modelSelect)) {
            foreach ($this->modelSelect as $alias => $fields) {
                foreach ($fields as $field) {
                    $this->select['fields'][] = $this->escapeField($field);
                }
            }
        }
    }

    /**
     * Resolve the join model class and build $joinsList
     *
     * @param string $type Join Type
     * @param array $args
     *
     * @return array $joinList
     *
     */
    private function resolveJoins(string $type, array $args)
    {
        if (class_exists($args[0])) {
            $model = new $args[0]();
            $alias = $model->alias ?? $model->table;

            $this->joinModelInstances[$alias] = $model;

            // add fields of the joined table by default
            // if ->select(...)  is not explicitly called
            if (!$this->isSelectFieldsGiven) {
                foreach ($model->fields as $field) {
                    $this->modelSelect[$alias][] = $this->aliasedField($alias, $field);
                }

                foreach ($model->customFields as $field) {
                    $this->modelSelect[$alias][] = $this->aliasedCustomField(
                        $alias,
                        $field,
                        $model->customFields[$field]
                    );
                }
            }

            $args[0] = $model->table . ' as ' . $alias;
        }

        return [$type, $args];
    }

    /**
     * Alias model field with table prefix
     *
     * @param string $alias
     * @param string $field
     *
     * @return $aliasedField
     *
     */
    private function aliasedField(string $alias, string $field)
    {
        return '`' . $alias . '`.`' . $field . '` as `' . $alias . '.' . $field . '`';
    }

    /**
     * Alias model custom fields with table prefix
     *
     * @param string $alias
     * @param string $field
     * @param string $customField
     *
     * @return $aliasedField
     *
     */
    private function aliasedCustomField(string $alias, string $field, string $customField)
    {
        return $customField . ' as `' . $alias . '.' . $field . '`';
    }
}