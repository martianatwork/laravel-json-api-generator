<?php
/**
 * Created by Cristian.
 * Date: 11/09/16 09:26 PM.
 */

namespace MartianAtWork\Coders\Model\Relations;

use Illuminate\Support\Fluent;
use MartianAtWork\Coders\Model\Model;
use MartianAtWork\Coders\Model\Relation;
use MartianAtWork\Support\Dumper;

abstract class HasOneOrMany implements Relation
{
    /**
     * @var Fluent
     */
    protected $command;

    /**
     * @var Model
     */
    protected $parent;

    /**
     * @var Model
     */
    protected $related;

    /**
     * HasManyWriter constructor.
     *
     * @param Fluent $command
     * @param Model $parent
     * @param Model $related
     */
    public function __construct(Fluent $command, Model $parent, Model $related) {
        $this->command = $command;
        $this->parent = $parent;
        $this->related = $related;
    }

    /**
     * @return string
     */
    abstract public function hint();

    /**
     * @return string
     */
    abstract public function name();

    /**
     * @return string
     */
    public function body() {
        return 'return $this->' . $this->jsonApiAlternative() . '();';
        $body = 'return $this->' . $this->method() . '(';
        $body .= $this->related->getQualifiedUserClassName() . '::class';
        if ($this->needsForeignKey()) {
            $foreignKey = $this->parent->usesPropertyConstants()
                ? $this->related->getQualifiedUserClassName() . '::' . strtoupper($this->foreignKey())
                : $this->foreignKey();
            $body .= ', ' . Dumper::export($foreignKey);
        }
        if ($this->needsLocalKey()) {
            $localKey = $this->related->usesPropertyConstants()
                ? $this->related->getQualifiedUserClassName() . '::' . strtoupper($this->localKey())
                : $this->localKey();
            $body .= ', ' . Dumper::export($localKey);
        }
        $body .= ');';
        return $body;
    }

    /**
     * @return string
     */
    abstract protected function method();

    /**
     * @return bool
     */
    protected function needsForeignKey() {
        $defaultForeignKey = $this->parent->getRecordName() . '_id';
        return $defaultForeignKey != $this->foreignKey() || $this->needsLocalKey();
    }

    /**
     * @return string
     */
    protected function foreignKey() {
        return $this->command->columns[0];
    }

    /**
     * @return bool
     */
    protected function needsLocalKey() {
        return $this->parent->getPrimaryKey() != $this->localKey();
    }

    /**
     * @return string
     */
    protected function localKey() {
        return $this->command->references[0];
    }

    /**
     * @inheritDoc
     */
    public function jsonApiAlternative() {
        if ($this instanceof HasOne) return 'hasOne';
        if ($this instanceof HasMany) return 'hasMany';
    }

    /**
     * @inheritDoc
     */
    public function jsonApiRule() {
        if ($this instanceof HasOne) return \CloudCreativity\LaravelJsonApi\Rules\HasOne::class;
        if ($this instanceof HasMany) return \CloudCreativity\LaravelJsonApi\Rules\HasMany::class;
    }
}
