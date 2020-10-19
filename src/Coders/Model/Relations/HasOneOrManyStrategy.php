<?php
/**
 * Created by Cristian.
 * Date: 11/09/16 09:26 PM.
 */

namespace MartianAtWork\Coders\Model\Relations;

use Illuminate\Support\Fluent;
use MartianAtWork\Coders\Model\Model;
use MartianAtWork\Coders\Model\Relation;

class HasOneOrManyStrategy implements Relation
{
    /**
     * @var Relation
     */
    protected $relation;

    /**
     * HasManyWriter constructor.
     *
     * @param Fluent $command
     * @param Model $parent
     * @param Model $related
     */
    public function __construct(Fluent $command, Model $parent, Model $related) {
        if (
            $related->isPrimaryKey($command) ||
            $related->isUniqueKey($command)
        ) {
            $this->relation = new HasOne($command, $parent, $related);
        } else {
            $this->relation = new HasMany($command, $parent, $related);
        }
    }

    /**
     * @return string
     */
    public function hint() {
        return $this->relation->hint();
    }

    /**
     * @return string
     */
    public function name() {
        return $this->relation->name();
    }

    /**
     * @return string
     */
    public function body() {
        return $this->relation->body();
    }


    /**
     * @inheritDoc
     */
    public function jsonApiAlternative() {
        if ($this->relation instanceof HasOne) return 'hasOne';
        if ($this->relation instanceof HasMany) return 'hasMany';
    }

    /**
     * @inheritDoc
     */
    public function jsonApiRule() {
        if ($this->relation instanceof HasOne) return \CloudCreativity\LaravelJsonApi\Rules\HasOne::class;
        if ($this->relation instanceof HasMany) return \CloudCreativity\LaravelJsonApi\Rules\HasMany::class;
    }
}
