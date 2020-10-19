<?php
/**
 * Created by Cristian.
 * Date: 11/09/16 12:11 PM.
 */

namespace MartianAtWork\Coders\Model;

use CloudCreativity\LaravelJsonApi\Eloquent\AbstractAdapter;
use CloudCreativity\LaravelJsonApi\Validation\AbstractValidators;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use MartianAtWork\Coders\Model\Relations\BelongsTo;
use MartianAtWork\Coders\Model\Relations\ReferenceFactory;
use MartianAtWork\Meta\Blueprint;
use Neomerx\JsonApi\Schema\SchemaProvider;
use RuntimeException;

class Model
{
    /**
     * @var array
     */
    protected $properties = [];
    /**
     * @var Relation[]
     */
    protected $relations = [];
    /**
     * @var Blueprint[]
     */
    protected $references = [];
    /**
     * @var array
     */
    protected $hidden = [];
    /**
     * @var array
     */
    protected $fillable = [];
    /**
     * @var array
     */
    protected $casts = [];
    /**
     * @var Mutator[]
     */
    protected $mutators = [];
    /**
     * @var Mutation[]
     */
    protected $mutations = [];
    /**
     * @var array
     */
    protected $dates = [];
    /**
     * @var array
     */
    protected $hints = [];
    /**
     * @var string
     */
    protected $namespace;
    /**
     * @var string
     */
    protected $parentClass;
    /**
     * @var bool
     */
    protected $timestamps = true;
    /**
     * @var string
     */
    protected $CREATED_AT;
    /**
     * @var string
     */
    protected $UPDATED_AT;
    /**
     * @var bool
     */
    protected $softDeletes = false;
    /**
     * @var string
     */
    protected $DELETED_AT;
    /**
     * @var bool
     */
    protected $showConnection = false;
    /**
     * @var string
     */
    protected $connection;
    /**
     * @var Fluent
     */
    protected $primaryKeys;
    /**
     * @var Fluent
     */
    protected $primaryKeyColumn;
    /**
     * @var int
     */
    protected $perPage;
    /**
     * @var string
     */
    protected $dateFormat;
    /**
     * @var bool
     */
    protected $loadRelations;
    /**
     * @var bool
     */
    protected $hasCrossDatabaseRelationships = false;
    /**
     * @var string
     */
    protected $tablePrefix = '';
    /**
     * @var string
     */
    protected $relationNameStrategy = '';
    /**
     * @var Blueprint
     */
    private $blueprint;
    /**
     * @var Factory
     */
    private $factory;
    /**
     * @var string
     */
    public $type;

    /**
     * ModelClass constructor.
     *
     * @param Blueprint $blueprint
     * @param Factory $factory
     * @param Mutator[] $mutators
     * @param bool $loadRelations
     * @param string $type
     */
    public function __construct(Blueprint $blueprint, Factory $factory, $mutators = [], $loadRelations = true, $type = 'model') {
        $this->blueprint = $blueprint;
        $this->factory = $factory;
        $this->loadRelations = $loadRelations;
        $this->mutators = $mutators;
        $this->type = $type;
        $this->configure();
        $this->fill();
    }

    protected function configure() {
        $this->withNamespace($this->config('namespace'));
        $this->withParentClass($this->config('parent'));
        // Timestamps settings
        $this->withTimestamps($this->config('timestamps.enabled', $this->config('timestamps', true)));
        $this->withCreatedAtField($this->config('timestamps.fields.CREATED_AT', $this->getDefaultCreatedAtField()));
        $this->withUpdatedAtField($this->config('timestamps.fields.UPDATED_AT', $this->getDefaultUpdatedAtField()));
        // Soft deletes settings
        $this->withSoftDeletes($this->config('soft_deletes.enabled', $this->config('soft_deletes', false)));
        $this->withDeletedAtField($this->config('soft_deletes.field', $this->getDefaultDeletedAtField()));
        // Connection settings
        $this->withConnection($this->config('connection', false));
        $this->withConnectionName($this->blueprint->connection());
        // Pagination settings
        $this->withPerPage($this->config('per_page', $this->getDefaultPerPage()));
        // Dates settings
        $this->withDateFormat($this->config('date_format', $this->getDefaultDateFormat()));
        // Table Prefix settings
        $this->withTablePrefix($this->config('table_prefix', $this->getDefaultTablePrefix()));
        // Relation name settings
        $this->withRelationNameStrategy($this->config('relation_name_strategy', $this->getDefaultRelationNameStrategy()));
        return $this;
    }

    /**
     * @param string $namespace
     *
     * @return $this
     */
    public function withNamespace($namespace) {
        $this->namespace = $namespace;
        return $this;
    }

    /**
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function config($key = null, $default = null) {
        return $this->factory->config($this->getBlueprint(), $key, $default);
    }

    /**
     * @return Blueprint
     */
    public function getBlueprint() {
        return $this->blueprint;
    }

    /**
     * @param string $parent
     *
     * @return $this
     */
    public function withParentClass($parent) {
        $this->parentClass = $parent;
        return $this;
    }

    /**
     * @param bool $timestampsEnabled
     *
     * @return $this
     */
    public function withTimestamps($timestampsEnabled) {
        $this->timestamps = $timestampsEnabled;
        return $this;
    }

    /**
     * @param string $field
     *
     * @return $this
     */
    public function withCreatedAtField($field) {
        $this->CREATED_AT = $field;
        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultCreatedAtField() {
        return Eloquent::CREATED_AT;
    }

    /**
     * @param string $field
     *
     * @return $this
     */
    public function withUpdatedAtField($field) {
        $this->UPDATED_AT = $field;
        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultUpdatedAtField() {
        return Eloquent::UPDATED_AT;
    }

    /**
     * @param bool $softDeletesEnabled
     *
     * @return $this
     */
    public function withSoftDeletes($softDeletesEnabled) {
        $this->softDeletes = $softDeletesEnabled;
        return $this;
    }

    /**
     * @param string $field
     *
     * @return $this
     */
    public function withDeletedAtField($field) {
        $this->DELETED_AT = $field;
        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultDeletedAtField() {
        return 'deleted_at';
    }

    /**
     * @param bool $showConnection
     */
    public function withConnection($showConnection) {
        $this->showConnection = $showConnection;
    }

    /**
     * @param string $connection
     */
    public function withConnectionName($connection) {
        $this->connection = $connection;
    }

    /**
     * @param $perPage
     */
    public function withPerPage($perPage) {
        $this->perPage = (int)$perPage;
    }

    /**
     * @return int
     */
    public function getDefaultPerPage() {
        return 15;
    }

    /**
     * @param string $format
     *
     * @return $this
     */
    public function withDateFormat($format) {
        $this->dateFormat = $format;
        return $this;
    }

    /**
     * @return string
     */
    public function getDefaultDateFormat() {
        return 'Y-m-d H:i:s';
    }

    /**
     * @param string $tablePrefix
     */
    public function withTablePrefix($tablePrefix) {
        $this->tablePrefix = $tablePrefix;
    }

    /**
     * @return string
     */
    public function getDefaultTablePrefix() {
        return '';
    }

    /**
     * @param string $relationNameStrategy
     */
    public function withRelationNameStrategy($relationNameStrategy) {
        $this->relationNameStrategy = $relationNameStrategy;
    }

    /**
     * @return string
     */
    public function getDefaultRelationNameStrategy() {
        return 'related';
    }

    /**
     * Parses the model information.
     */
    protected function fill() {
        $this->primaryKeys = $this->blueprint->primaryKey();
        // Process columns
        foreach ($this->blueprint->columns() as $column) {
            $this->parseColumn($column);
        }
        if (!$this->loadRelations) {
            return;
        }
        foreach ($this->blueprint->relations() as $relation) {
            $model = $this->makeRelationModel($relation);
            $belongsTo = new BelongsTo($relation, $this, $model);
            $this->relations[$belongsTo->name()] = $belongsTo;
        }
        foreach ($this->factory->referencing($this) as $related) {
            $factory = new ReferenceFactory($related, $this);
            $references = $factory->make();
            foreach ($references as $reference) {
                $this->relations[$reference->name()] = $reference;
            }
        }
    }

    /**
     * @param Fluent $column
     */
    protected function parseColumn(Fluent $column) {
        // TODO: Check type cast is OK
        $cast = $column->type;
        $propertyName = $this->usesPropertyConstants() ? 'self::' . strtoupper($column->name) : $column->name;
        // Due to some casting problems when converting null to a Carbon instance,
        // we are going to treat Soft Deletes field as string.
        if ($column->name == $this->getDeletedAtField()) {
            $cast = 'string';
        }
        // Track dates
        if ($cast == 'date') {
            $this->dates[] = $propertyName;
        } // Track attribute casts
        elseif ($cast != 'string') {
            $this->casts[$propertyName] = $cast;
        }
        foreach ($this->config('casts', []) as $pattern => $casting) {
            if (Str::is($pattern, $column->name)) {
                $this->casts[$propertyName] = $cast = $casting;
                break;
            }
        }
        if ($this->isHidden($column->name)) {
            $this->hidden[] = $propertyName;
        }
        if ($this->isFillable($column->name)) {
            $this->fillable[] = $propertyName;
        }
        $this->mutate($column->name);
        // Track comment hints
        if (!empty($column->comment)) {
            $this->hints[$column->name] = $column->comment;
        }
        // Track PHP type hints
        $hint = $this->phpTypeHint($cast);
        $this->properties[$column->name] = $hint;
        if ($column->name == $this->getPrimaryKey()) {
            $this->primaryKeyColumn = $column;
        }
    }

    /**
     * @return bool
     */
    public function usesPropertyConstants() {
        return $this->config('with_property_constants', false);
    }

    /**
     * @return string
     */
    public function getDeletedAtField() {
        return $this->DELETED_AT;
    }

    /**
     * @param string $column
     *
     * @return bool
     */
    public function isHidden($column) {
        $attributes = $this->config('hidden', []);
        if (!is_array($attributes)) {
            throw new RuntimeException('Config field [hidden] must be an array of attributes to hide from array or json.');
        }
        foreach ($attributes as $pattern) {
            if (Str::is($pattern, $column)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string $column
     *
     * @return bool
     */
    public function isFillable($column) {
        $guarded = $this->config('guarded', []);
        if (!is_array($guarded)) {
            throw new RuntimeException('Config field [guarded] must be an array of attributes to protect from mass assignment.');
        }
        $protected = [
            $this->getCreatedAtField(),
            $this->getUpdatedAtField(),
            $this->getDeletedAtField(),
        ];
        if ($this->primaryKeys->columns) {
            $protected = array_merge($protected, $this->primaryKeys->columns);
        }
        foreach (array_merge($guarded, $protected) as $pattern) {
            if (Str::is($pattern, $column)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @return string
     */
    public function getCreatedAtField() {
        return $this->CREATED_AT;
    }

    /**
     * @return string
     */
    public function getUpdatedAtField() {
        return $this->UPDATED_AT;
    }

    /**
     * @param string $column
     */
    protected function mutate($column) {
        foreach ($this->mutators as $mutator) {
            if ($mutator->applies($column, $this->getBlueprint())) {
                $this->mutations[] = new Mutation(
                    $mutator->getName($column, $this),
                    $mutator->getBody($column, $this)
                );
            }
        }
    }

    /**
     * @param string $castType
     *
     * @return string
     * @todo Make tests
     *
     */
    public function phpTypeHint($castType) {
        switch ($castType) {
            case 'object':
                return '\stdClass';
            case 'array':
            case 'json':
                return 'array';
            case 'collection':
                return '\Illuminate\Support\Collection';
            case 'date':
                return '\Carbon\Carbon';
            case 'binary':
                return 'string';
            default:
                return $castType;
        }
    }

    /**
     * @return string
     * @todo: Improve it
     */
    public function getPrimaryKey() {
        if (empty($this->primaryKeys->columns)) {
            return;
        }
        return $this->primaryKeys->columns[0];
    }

    /**
     * @param Fluent $relation
     *
     * @return $this|Model
     */
    public function makeRelationModel(Fluent $relation) {
        list($database, $table) = array_values($relation->on);
        if ($this->blueprint->is($database, $table)) {
            return $this;
        }
        return $this->factory->makeModel($database, $table, false);
    }

    /**
     * @return string
     */
    public function getSchema() {
        return $this->blueprint->schema();
    }

    /**
     * @return string
     */
    public function getTableForQuery() {
        return $this->shouldQualifyTableName()
            ? $this->getQualifiedTable()
            : $this->getTable();
    }

    /**
     * @return bool
     */
    public function shouldQualifyTableName() {
        return $this->config('qualified_tables', false);
    }

    /**
     * @return string
     */
    public function getQualifiedTable() {
        return $this->blueprint->qualifiedTable();
    }

    /**
     * @return string
     */
    public function getTable($andRemovePrefix = false) {
        if ($andRemovePrefix) {
            return $this->removeTablePrefix($this->blueprint->table());
        }
        return $this->blueprint->table();
    }

    /**
     * @param string $table
     */
    public function removeTablePrefix($table) {
        if (($this->shouldRemoveTablePrefix()) && (substr($table, 0, strlen($this->tablePrefix)) == $this->tablePrefix)) {
            $table = substr($table, strlen($this->tablePrefix));
        }
        return $table;
    }

    /**
     * @return string
     */
    public function shouldRemoveTablePrefix() {
        return !empty($this->tablePrefix);
    }

    /**
     * @param Blueprint[] $references
     */
    public function withReferences($references) {
        $this->references = $references;
    }

    /**
     * @return string
     */
    public function getRelationNameStrategy() {
        return $this->relationNameStrategy;
    }

    /**
     * @return string
     */
    public function getBaseNamespace() {
        return $this->usesBaseFiles()
            ? $this->getNamespace() . "\\Base"
            : $this->getNamespace();
    }

    /**
     * @return bool
     */
    public function usesBaseFiles() {
        return $this->config('base_files', false);
        switch ($this->type) {
            case 'adapter': return  false;
            default: $this->config('base_files', false);
        }
    }

    /**
     * @return string
     */
    public function getNamespace() {
        return $this->namespace. '\\'. $this->getClassName(true);
    }

    /**
     * @return string
     */
    public function getParentClass() {
        switch ($this->type) {
            case 'validators': return AbstractValidators::class;
            case 'schema': return SchemaProvider::class;
            case 'adapter': return AbstractAdapter::class;
            default: return $this->parentClass;;
        }
    }
    /**
     * @return string[]
     */
    public function getAdditionalClass() {
        switch ($this->type) {
            case 'adapter': return [
                'CloudCreativity\LaravelJsonApi\Eloquent\AbstractAdapter',
                'CloudCreativity\LaravelJsonApi\Pagination\StandardStrategy',
                'Illuminate\Database\Eloquent\Builder',
                'Illuminate\Support\Collection',
            ];
            default: return [];
        }
    }

    /**
     * @return string
     */
    public function getQualifiedUserClassName() {
        return '\\' . $this->getNamespace() . '\\' . $this->getClassName();
    }

    /**
     * @param bool $plural
     * @return string
     */
    public function getClassName($plural = false) {
        if ($this->shouldLowerCaseTableName()) {
            return Str::studly(Str::lower($this->getRecordName($plural)));
        }
        return Str::studly($this->getRecordName($plural));
    }

    /**
     * @return string
     */
    public function getTypeClassName() {
        if ($this->shouldLowerCaseTableName()) {
            return Str::studly(Str::lower($this->type));
        }
        return Str::studly($this->type);
    }

    /**
     * @return bool
     */
    public function shouldLowerCaseTableName() {
        return (bool)$this->config('lower_table_name_first', false);
    }

    /**
     * @param $plural
     * @return string
     */
    public function getRecordName($plural = true) {
        if($plural) {
            return $this->removeTablePrefix($this->blueprint->table());
        }
        if ($this->shouldPluralizeTableName()) {
            return Str::singular($this->removeTablePrefix($this->blueprint->table()));
        }
        return $this->removeTablePrefix($this->blueprint->table());
    }

    /**
     * @return bool
     */
    public function shouldPluralizeTableName() {
        $pluralize = (bool)$this->config('pluralize', true);
        $overridePluralizeFor = $this->config('override_pluralize_for', []);
        if (count($overridePluralizeFor) > 0) {
            foreach ($overridePluralizeFor as $except) {
                if ($except == $this->getTable()) {
                    return !$pluralize;
                }
            }
        }
        return $pluralize;
    }

    /**
     * @return bool
     */
    public function hasCustomCreatedAtField() {
        return $this->usesTimestamps() &&
            $this->getCreatedAtField() != $this->getDefaultCreatedAtField();
    }

    /**
     * @return bool
     */
    public function usesTimestamps() {
        return $this->timestamps &&
            $this->blueprint->hasColumn($this->getCreatedAtField()) &&
            $this->blueprint->hasColumn($this->getUpdatedAtField());
    }

    /**
     * @return bool
     */
    public function hasCustomUpdatedAtField() {
        return $this->usesTimestamps() &&
            $this->getUpdatedAtField() != $this->getDefaultUpdatedAtField();
    }

    /**
     * @return bool
     */
    public function hasCustomDeletedAtField() {
        return $this->usesSoftDeletes() &&
            $this->getDeletedAtField() != $this->getDefaultDeletedAtField();
    }

    /**
     * @return bool
     */
    public function usesSoftDeletes() {
        return $this->softDeletes &&
            $this->blueprint->hasColumn($this->getDeletedAtField());
    }

    /**
     * @return array
     */
    public function getTraits() {
        $traits = $this->config('use', []);
        if (!is_array($traits)) {
            throw new RuntimeException('Config use must be an array of valid traits to append to each model.');
        }
        if ($this->usesSoftDeletes()) {
            $traits = array_merge([SoftDeletes::class], $traits);
        }
        return $traits;
    }

    /**
     * @return bool
     */
    public function needsTableName() {
        return false === $this->shouldQualifyTableName() ||
            $this->shouldRemoveTablePrefix() ||
            $this->blueprint->table() != Str::plural($this->getRecordName()) ||
            !$this->shouldPluralizeTableName();
    }

    /**
     * @return bool
     */
    public function shouldShowConnection() {
        return (bool)$this->showConnection;
    }

    /**
     * @return string
     */
    public function getConnectionName() {
        return $this->connection;
    }

    /**
     * @return bool
     */
    public function hasCustomPrimaryKey() {
        return is_array($this->primaryKeys->columns) && count($this->primaryKeys->columns) == 1 &&
            $this->getPrimaryKey() != $this->getDefaultPrimaryKeyField();
    }

    /**
     * @return string
     */
    public function getDefaultPrimaryKeyField() {
        return 'id';
    }

    /**
     * @return bool
     * @todo: Check whether it is necessary
     */
    public function hasCustomPrimaryKeyCast() {
        return $this->getPrimaryKeyType() != $this->getDefaultPrimaryKeyType();
    }

    /**
     * @return string
     * @todo: check
     */
    public function getPrimaryKeyType() {
        return $this->primaryKeyColumn->type;
    }

    /**
     * @return string
     */
    public function getDefaultPrimaryKeyType() {
        return 'int';
    }

    /**
     * @return bool
     */
    public function doesNotAutoincrement() {
        return !$this->autoincrement();
    }

    /**
     * @return bool
     */
    public function autoincrement() {
        if ($this->primaryKeyColumn) {
            return $this->primaryKeyColumn->autoincrement === true;
        }
        return false;
    }

    /**
     * @return int
     */
    public function getPerPage() {
        return $this->perPage;
    }

    /**
     * @return bool
     */
    public function hasCustomPerPage() {
        return $this->perPage != $this->getDefaultPerPage();
    }

    /**
     * @return string
     */
    public function getDateFormat() {
        return $this->dateFormat;
    }

    /**
     * @return bool
     */
    public function hasCustomDateFormat() {
        return $this->dateFormat != $this->getDefaultDateFormat();
    }

    /**
     * @return bool
     */
    public function hasCasts() {
        return !empty($this->getCasts());
    }

    /**
     * @return array
     */
    public function getCasts() {
        if (
            array_key_exists($this->getPrimaryKey(), $this->casts) &&
            $this->autoincrement()
        ) {
            unset($this->casts[$this->getPrimaryKey()]);
        }
        return $this->casts;
    }

    /**
     * @return bool
     */
    public function hasDates() {
        return !empty($this->getDates());
    }

    /**
     * @return array
     */
    public function getDates() {
        return array_diff($this->dates, [$this->CREATED_AT, $this->UPDATED_AT]);
    }

    /**
     * @return bool
     */
    public function doesNotUseSnakeAttributes() {
        return !$this->usesSnakeAttributes();
    }

    /**
     * @return bool
     */
    public function usesSnakeAttributes() {
        return (bool)$this->config('snake_attributes', true);
    }

    /**
     * @return bool
     */
    public function hasHints() {
        return !empty($this->getHints());
    }

    /**
     * @return array
     */
    public function getHints() {
        return $this->hints;
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function hasProperty($name) {
        return array_key_exists($name, $this->getProperties());
    }

    /**
     * @return array
     */
    public function getProperties() {
        return $this->properties;
    }

    /**
     * @return Relation[]
     */
    public function getRelations() {
        return $this->relations;
    }

    /**
     * @return bool
     */
    public function hasRelations() {
        return !empty($this->relations);
    }

    /**
     * @return Mutation[]
     */
    public function getMutations() {
        return $this->mutations;
    }

    /**
     * @return bool
     */
    public function hasHidden() {
        return !empty($this->hidden);
    }

    /**
     * @return array
     */
    public function getHidden() {
        return $this->hidden;
    }

    /**
     * @return bool
     */
    public function hasFillable() {
        return !empty($this->fillable);
    }

    /**
     * @return array
     */
    public function getFillable() {
        return $this->fillable;
    }

    /**
     * @return string
     */
    public function getAttributesSchema() {
        $arr = "";
        foreach ($this->properties as $key => $property) {
            if(in_array($key, ['id'])) continue;
            if(in_array($property, ['\Carbon\Carbon'])) {
                $arr .= "            '$key' => \$resource->$key,\n";
            } else {
                $arr .= "            '$key' => \$resource->$key,\n";
            }
        }
        return "return [\n$arr        ];";
    }

    /**
     * @return array
     */
    public function getAttributesRules() {
        $arr = [];
        foreach ($this->properties as $key => $property) {
            if(in_array($key, ['id'])) continue;
            $val = null;
            switch ($property) {
                case 'string': $val = 'alpha_num'; break;
                case '\Carbon\Carbon': $val = 'datetime'; break;
                case 'array': $val = 'array'; break;
                case 'bool': $val = 'boolean'; break;
                case 'int': $val = 'numeric'; break;
                default:
            }
            $arr = array_merge($arr, [$key => $val]);
        }
        return $arr;
    }

    /**
     * @return array
     */
    public function getAttributesSorts() {
        $arr = [];
        foreach ($this->properties as $key => $property) {
            if(!in_array($property, ['int','\Carbon\Carbon'])) continue;
            $arr = array_merge($arr, [$key]);
        }
        return $arr;
    }
    /**
     * @return array
     */
    public function getAttributesFiltering() {
        $arr = [];
        foreach ($this->properties as $key => $property) {
            if(in_array($property, ['array'])) continue;
            $arr = array_merge($arr, [$key]);
        }
        return $arr;
    }

    public function getRelationPaths() {
        $arr = [];
        foreach ($this->relations as $relation) {
            array_push($arr, $relation->name());
        }
        return $arr;
    }
    /**
     * @param Fluent $command
     *
     * @return bool
     */
    public function isPrimaryKey(Fluent $command) {
        foreach ((array)$this->primaryKeys->columns as $column) {
            if (!in_array($column, $command->columns)) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param Fluent $command
     *
     * @return bool
     */
    public function isUniqueKey(Fluent $command) {
        return $this->blueprint->isUniqueKey($command);
    }

    /**
     * @return int
     */
    public function indentWithSpace() {
        return (int)$this->config('indent_with_space', 0);
    }

    /**
     * @return bool
     */
    public function usesHints() {
        return $this->config('hints', false);
    }

    /**
     * @return bool
     */
    public function doesNotUseBaseFiles() {
        switch ($this->type) {
            case 'validators':
            case 'schema':
            case 'adapter':
                return true;
            default: return !$this->usesBaseFiles();
        }
    }
}
