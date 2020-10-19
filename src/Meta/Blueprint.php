<?php
/**
 * Created by Cristian.
 * Date: 18/09/16 08:19 PM.
 */

namespace MartianAtWork\Meta;

use Illuminate\Support\Fluent;
use InvalidArgumentException;

class Blueprint
{
    /**
     * @var string
     */
    protected $connection;

    /**
     * @var string
     */
    protected $schema;

    /**
     * @var string
     */
    protected $table;

    /**
     * @var Fluent[]
     */
    protected $columns = [];

    /**
     * @var Fluent[]
     */
    protected $indexes = [];

    /**
     * @var Fluent[]
     */
    protected $unique = [];

    /**
     * @var Fluent[]
     */
    protected $relations = [];

    /**
     * @var Fluent
     */
    protected $primaryKey;

    /**
     * @var bool
     */
    protected $isView;

    /**
     * Blueprint constructor.
     *
     * @param string $connection
     * @param string $schema
     * @param string $table
     */
    public function __construct($connection, $schema, $table, $isView = false) {
        $this->connection = $connection;
        $this->schema = $schema;
        $this->table = $table;
        $this->isView = $isView;
    }

    /**
     * @param Fluent $column
     *
     * @return $this
     */
    public function withColumn(Fluent $column) {
        $this->columns[$column->name] = $column;
        return $this;
    }

    /**
     * @return Fluent[]
     */
    public function columns() {
        return $this->columns;
    }

    /**
     * @param string $name
     *
     * @return Fluent
     */
    public function column($name) {
        if (!$this->hasColumn($name)) {
            throw new InvalidArgumentException("Column [$name] does not belong to table [{$this->qualifiedTable()}]");
        }
        return $this->columns[$name];
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasColumn($name) {
        return array_key_exists($name, $this->columns);
    }

    /**
     * @return string
     */
    public function qualifiedTable() {
        return $this->schema() . '.' . $this->table();
    }

    /**
     * @return string
     */
    public function schema() {
        return $this->schema;
    }

    /**
     * @return string
     */
    public function table() {
        return $this->table;
    }

    /**
     * @param Fluent $index
     *
     * @return $this
     */
    public function withIndex(Fluent $index) {
        $this->indexes[] = $index;
        if ($index->name == 'unique') {
            $this->unique[] = $index;
        }
        return $this;
    }

    /**
     * @return Fluent[]
     */
    public function indexes() {
        return $this->indexes;
    }

    /**
     * @param Fluent $index
     *
     * @return $this
     */
    public function withRelation(Fluent $index) {
        $this->relations[] = $index;
        return $this;
    }

    /**
     * @param Fluent $primaryKey
     *
     * @return $this
     */
    public function withPrimaryKey(Fluent $primaryKey) {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    /**
     * @return Fluent
     */
    public function primaryKey() {
        if ($this->primaryKey) {
            return $this->primaryKey;
        }
        if (!empty($this->unique)) {
            return current($this->unique);
        }
        $nullPrimaryKey = new Fluent(['columns' => []]);
        return $nullPrimaryKey;
    }

    /**
     * @return bool
     */
    public function hasCompositePrimaryKey() {
        return count($this->primaryKey->columns) > 1;
    }

    /**
     * @return string
     */
    public function connection() {
        return $this->connection;
    }

    /**
     * @param Blueprint $table
     *
     * @return array
     */
    public function references(self $table) {
        $references = [];
        foreach ($this->relations() as $relation) {
            list($foreignDatabase, $foreignTable) = array_values($relation->on);
            if ($table->is($foreignDatabase, $foreignTable)) {
                $references[] = $relation;
            }
        }
        return $references;
    }

    /**
     * @return Fluent[]
     */
    public function relations() {
        return $this->relations;
    }

    /**
     * @param string $database
     * @param string $table
     *
     * @return bool
     */
    public function is($database, $table) {
        return $database == $this->schema() && $table == $this->table();
    }

    /**
     * @param Fluent $constraint
     *
     * @return bool
     */
    public function isUniqueKey(Fluent $constraint) {
        foreach ($this->unique as $index) {

            // We only need to consider cases, when UNIQUE KEY is presented by only ONE column
            if (count($index->columns) === 1 && isset($index->columns[0])) {
                if (in_array($index->columns[0], $constraint->columns)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isView() {
        return $this->isView;
    }
}
