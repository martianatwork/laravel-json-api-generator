<?php
/**
 * Created by Cristian.
 * Date: 02/10/16 07:37 PM.
 */

namespace MartianAtWork\Meta;

use ArrayIterator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\MySqlConnection;
use IteratorAggregate;
use MartianAtWork\Meta\MySql\Schema as MySqlSchema;
use RuntimeException;

class ModelManager implements IteratorAggregate
{
    /**
     * @var array
     */
    protected static $lookup = [
        MySqlConnection::class => MySqlSchema::class,
    ];
    /**
     * @var Schema[]
     */
    protected $schemas = [];
    /**
     * @var ConnectionInterface
     */
    private $connection;

    /**
     * SchemaManager constructor.
     *
     * @param ConnectionInterface $connection
     */
    public function __construct(ConnectionInterface $connection) {
        $this->connection = $connection;
        $this->boot();
    }

    /**
     * Load all schemas from this connection.
     */
    public function boot() {
        if (!$this->hasMapping()) {
            throw new RuntimeException("There is no Schema Mapper registered for [{$this->type()}] connection.");
        }
        $schemas = forward_static_call([$this->getMapper(), 'schemas'], $this->connection);
        foreach ($schemas as $schema) {
            $this->make($schema);
        }
    }

    /**
     * @return bool
     */
    protected function hasMapping() {
        return array_key_exists($this->type(), static::$lookup);
    }

    /**
     * @return string
     */
    protected function type() {
        return get_class($this->connection);
    }

    /**
     * @return string
     */
    protected function getMapper() {
        return static::$lookup[$this->type()];
    }

    /**
     * @param string $schema
     *
     * @return Schema
     */
    public function make($schema) {
        if (array_key_exists($schema, $this->schemas)) {
            return $this->schemas[$schema];
        }
        return $this->schemas[$schema] = $this->makeMapper($schema);
    }

    /**
     * @param string $schema
     *
     * @return Schema
     */
    protected function makeMapper($schema) {
        $mapper = $this->getMapper();
        return new $mapper($schema, $this->connection);
    }

    /**
     * Register a new connection mapper.
     *
     * @param string $connection
     * @param string $mapper
     */
    public static function register($connection, $mapper) {
        static::$lookup[$connection] = $mapper;
    }

    /**
     * Get Iterator for schemas.
     *
     * @return ArrayIterator
     */
    public function getIterator() {
        return new ArrayIterator($this->schemas);
    }
}
