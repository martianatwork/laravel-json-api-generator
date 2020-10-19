<?php
/**
 * Created by Cristian.
 * Date: 02/10/16 07:37 PM.
 */

namespace MartianAtWork\Meta;

use ArrayIterator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\SQLiteConnection;
use IteratorAggregate;
use MartianAtWork\Meta\MySql\Schema as MySqlSchema;
use MartianAtWork\Meta\Postgres\Schema as PostgresSchema;
use MartianAtWork\Meta\Sqlite\Schema as SqliteSchema;
use RuntimeException;

class SchemaManager implements IteratorAggregate
{
    /**
     * @var array
     */
    protected static $lookup = [
        MySqlConnection::class => MySqlSchema::class,
        SQLiteConnection::class => SqliteSchema::class,
        PostgresConnection::class => PostgresSchema::class,
        \Larapack\DoctrineSupport\Connections\MySqlConnection::class => MySqlSchema::class,
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
     * @var null
     */
    private $schema;

    /**
     * SchemaManager constructor.
     *
     * @param ConnectionInterface $connection
     * @param null $schema
     */
    public function __construct(ConnectionInterface $connection, $schema = null) {
        $this->connection = $connection;
        $this->schema = $schema;
        $this->boot();
    }

    /**
     * Load all schemas from this connection.
     */
    public function boot() {
        if (!$this->hasMapping()) {
            throw new RuntimeException("There is no Schema Mapper registered for [{$this->type()}] connection.");
        }
        if($this->schema) {
            $this->make($this->schema);
            return;
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
