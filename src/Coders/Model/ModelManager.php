<?php
/**
 * Created by Cristian.
 * Date: 02/10/16 08:24 PM.
 */

namespace MartianAtWork\Coders\Model;

use ArrayIterator;
use Illuminate\Support\Arr;
use IteratorAggregate;

class ModelManager implements IteratorAggregate
{
    /**
     * @var Factory
     */
    protected $factory;

    /**
     * @var Model[]
     */
    protected $models = [];

    /**
     * ModelManager constructor.
     *
     * @param Factory $factory
     */
    public function __construct(Factory $factory) {
        $this->factory = $factory;
    }

    /**
     * @param string $schema
     * @param string $table
     * @param Mutator[] $mutators
     * @param bool $withRelations
     *
     * @param string $type
     * @return Model
     */
    public function make($schema, $table, $mutators = [], $withRelations = true, $type = 'model') {
        $mapper = $this->factory->makeSchema($schema);
        $blueprint = $mapper->table($table);
        if (Arr::has($this->models, [$blueprint->qualifiedTable(),$type])) {
            return $this->models[$schema][$table][$type];
        }
        $model = new Model($blueprint, $this->factory, $mutators, $withRelations, $type);
        if ($withRelations) {
            $this->models[$schema][$table][$type] = $model;
        }
        return $model;
    }

    /**
     * Get Models iterator.
     *
     * @return ArrayIterator
     */
    public function getIterator() {
        return new ArrayIterator($this->models);
    }
}
