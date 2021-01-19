<?php
/**
 * Created by Cristian.
 * Date: 19/09/16 11:58 PM.
 */

namespace MartianAtWork\Coders\Model;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use MartianAtWork\Meta\Blueprint;
use MartianAtWork\Meta\Schema;
use MartianAtWork\Meta\SchemaManager;
use MartianAtWork\Support\Classify;

class Factory
{
    /**
     * @var SchemaManager
     */
    protected $schemas = [];
    /**
     * @var Filesystem
     */
    protected $files;
    /**
     * @var Classify
     */
    protected $class;
    /**
     * @var Config
     */
    protected $config;
    /**
     * @var ModelManager
     */
    protected $models;
    /**
     * @var Mutator[]
     */
    protected $mutators = [];
    /**
     * @var DatabaseManager
     */
    private $db;

    /**
     * ModelsFactory constructor.
     *
     * @param DatabaseManager $db
     * @param Filesystem $files
     * @param Classify $writer
     * @param Config $config
     */
    public function __construct(DatabaseManager $db, Filesystem $files, Classify $writer, Config $config) {
        $this->db = $db;
        $this->files = $files;
        $this->config = $config;
        $this->class = $writer;
    }

    /**
     * @return Mutator
     */
    public function mutate() {
        return $this->mutators[] = new Mutator();
    }

    /**
     * @param string $schema
     */
    public function map($schema) {
        if (!isset($this->schemas)) {
            $this->on();
        }
        $mapper = $this->makeSchema($schema);
        foreach ($mapper->tables() as $blueprint) {
            if ($this->shouldTakeOnly($blueprint) && $this->shouldNotExclude($blueprint)) {
                $this->create($mapper->schema(), $blueprint->table());
            }
        }
    }

    /**
     * Select connection to work with.
     *
     * @param string $connection
     *
     * @param null $schema
     * @return $this
     */
    public function on($connection = null, $schema = null) {
        $this->schemas = new SchemaManager($this->db->connection($connection), $schema);
        return $this;
    }

    /**
     * @param string $schema
     *
     * @return Schema
     */
    public function makeSchema($schema) {
        return $this->schemas->make($schema);
    }

    /**
     * @param Blueprint $blueprint
     *
     * @return bool
     */
    protected function shouldTakeOnly(Blueprint $blueprint) {
        if ($patterns = $this->config($blueprint, 'only', [])) {
            foreach ($patterns as $pattern) {
                if (Str::is($pattern, $blueprint->table())) {
                    return true;
                }
            }
            return false;
        }
        return true;
    }

    /**
     * @param Blueprint|null $blueprint
     * @param string $key
     * @param mixed $default
     *
     * @return mixed|Config
     */
    public function config(Blueprint $blueprint = null, $key = null, $default = null) {
        if (is_null($blueprint)) {
            return $this->config;
        }
        return $this->config->get($blueprint, $key, $default);
    }

    /**
     * @param Blueprint $blueprint
     *
     * @return bool
     */
    protected function shouldNotExclude(Blueprint $blueprint) {
        foreach ($this->config($blueprint, 'except', []) as $pattern) {
            if (Str::is($pattern, $blueprint->table())) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param string $schema
     * @param string $table
     * @throws FileNotFoundException
     */
    public function create($schema, $table) {
        foreach (['adapter','schema','validators'] as $type) {
            $model = $this->makeModel($schema, $table, true, $type);
            $template = $this->prepareTemplate($model, $type);
            $file = $this->fillTemplate($template, $model, $type);

            if ($model->indentWithSpace()) {
                $file = str_replace("\t", str_repeat(' ', $model->indentWithSpace()), $file);
            }
            $this->files->put($this->modelPath($model, $model->usesBaseFiles() ? ['Base'] : []), $file);

            if ($this->needsUserFile($model)) {
                $this->createUserFile($model, "user_$type");
            }
        }
    }

    /**
     * @param string $schema
     * @param string $table
     *
     * @param bool $withRelations
     *
     * @param string $type
     * @return Model
     */
    public function makeModel($schema, $table, $withRelations = true, $type = 'model') {
        return $this->models()->make($schema, $table, $this->mutators, $withRelations, $type);
    }

    /**
     * @return ModelManager
     */
    protected function models() {
        if (!isset($this->models)) {
            $this->models = new ModelManager($this);
        }
        return $this->models;
    }

    /**
     * @param Model $model
     * @param string $name
     *
     * @return string
     * @throws FileNotFoundException
     */
    protected function prepareTemplate(Model $model, $name) {
        $defaultFile = $this->path([__DIR__, 'Templates', $name]);
        $file = $this->config($model->getBlueprint(), "*.template.$name", $defaultFile);
        return $this->files->get($file);
    }

    /**
     * @param array $pieces
     *
     * @return string
     */
    protected function path($pieces) {
        return implode(DIRECTORY_SEPARATOR, (array)$pieces);
    }

    /**
     * @param string $template
     * @param Model $model
     *
     * @param null $type
     * @return mixed
     */
    protected function fillTemplate($template, Model $model, $type = null) {
        $template = str_replace('{{namespace}}', $model->getBaseNamespace(), $template);
        $template = str_replace('{{class}}', Str::studly($type), $template);
//        $properties = $this->properties($model);
        $usedClasses = $this->extractUsedClasses($properties);
//        $template = str_replace('{{properties}}', $properties, $template);
        $parentClass = $model->getParentClass();
        $additionalClass = $model->getAdditionalClass();
        $usedClasses = array_merge($usedClasses, $additionalClass);
        $usedClasses = array_merge($usedClasses, $this->extractUsedClasses($parentClass));
        $usedClasses = array_merge($usedClasses, ["App\\Models\\".$model->getClassName()]);
        $template = str_replace('{{parent}}', $parentClass, $template);
        $body = $this->body($model);
        $usedClasses = array_merge($usedClasses, $this->extractUsedClasses($body));
        $template = str_replace('{{body}}', $body, $template);
        $usedClasses = array_unique($usedClasses);
        $usedClassesSection = $this->formatUsedClasses(
            $model->getBaseNamespace(),
            $usedClasses
        );
        $template = str_replace('{{imports}}', $usedClassesSection, $template);
        return $template;
    }

    /**
     * @param Model $model
     *
     * @return string
     */
    protected function properties(Model $model) {
        // Process property annotations
        $annotations = '';
        foreach ($model->getProperties() as $name => $hint) {
            $annotations .= $this->class->annotation('property', "$hint \$$name");
        }
        if ($model->hasRelations()) {
            // Add separation between model properties and model relations
            $annotations .= "\n * ";
        }
        foreach ($model->getRelations() as $name => $relation) {
            // TODO: Handle collisions, perhaps rename the relation.
            if ($model->hasProperty($name)) {
                continue;
            }
            $annotations .= $this->class->annotation('property', $relation->hint() . " \$$name");
        }
        return $annotations;
    }

    /**
     * Extract and replace fully-qualified class names from placeholder.
     *
     * @param string $placeholder Placeholder to extract class names from. Rewrites value to content without FQN
     *
     * @return array Extracted FQN
     */
    private function extractUsedClasses(&$placeholder) {
        $classNamespaceRegExp = '/([\\\\a-zA-Z0-9_]*\\\\[\\\\a-zA-Z0-9_]*)/';
        $matches = [];
        $usedInModelClasses = [];
        if (preg_match_all($classNamespaceRegExp, $placeholder, $matches)) {
            foreach ($matches[1] as $match) {
                $usedClassName = $match;
                $usedInModelClasses[] = trim($usedClassName, '\\');
                $namespaceParts = explode('\\', $usedClassName);
                $resultClassName = array_pop($namespaceParts);
                $placeholder = str_replace($usedClassName, $resultClassName, $placeholder);
            }
        }
        return array_unique($usedInModelClasses);
    }

    /**
     * @param Model $model
     *
     * @return string
     */
    protected function body(Model $model) {
        $body = '';
        $model_name = $model->getClassName();

        foreach ($model->getTraits() as $trait) {
            $body .= $this->class->mixin($trait);
        }
        $excludedConstants = [];
        if ($model->hasCustomCreatedAtField()) {
            $body .= $this->class->constant('CREATED_AT', $model->getCreatedAtField());
            $excludedConstants[] = $model->getCreatedAtField();
        }
        if ($model->hasCustomUpdatedAtField()) {
            $body .= $this->class->constant('UPDATED_AT', $model->getUpdatedAtField());
            $excludedConstants[] = $model->getUpdatedAtField();
        }
        if ($model->hasCustomDeletedAtField()) {
            $body .= $this->class->constant('DELETED_AT', $model->getDeletedAtField());
            $excludedConstants[] = $model->getDeletedAtField();
        }
        if ($model->usesPropertyConstants()) {
            // Take all properties and exclude already added constants with timestamps.
            $properties = array_keys($model->getProperties());
            $properties = array_diff($properties, $excludedConstants);
            foreach ($properties as $property) {
                $body .= $this->class->constant(strtoupper($property), $property);
            }
        }
        $body = trim($body, "\n");
        // Separate constants from fields only if there are constants.
        if (!empty($body)) {
            $body .= "\n";
        }
        // Append connection name when required
//        if ($model->shouldShowConnection()) {
//            $body .= $this->class->field('connection', $model->getConnectionName());
//        }
        // When table is not plural, append the table name
//        if ($model->needsTableName()) {
//            $body .= $this->class->field('table', $model->getTableForQuery());
//        }
//        if ($model->hasCustomPrimaryKey()) {
//            $body .= $this->class->field('primaryKey', $model->getPrimaryKey());
//        }
//        if ($model->doesNotAutoincrement()) {
//            $body .= $this->class->field('incrementing', false, ['visibility' => 'public']);
//        }
//        if ($model->hasCustomPerPage()) {
//            $body .= $this->class->field('perPage', $model->getPerPage());
//        }
//        if (!$model->usesTimestamps()) {
//            $body .= $this->class->field('timestamps', false, ['visibility' => 'public']);
//        }
//        if ($model->hasCustomDateFormat()) {
//            $body .= $this->class->field('dateFormat', $model->getDateFormat());
//        }
//        if ($model->doesNotUseSnakeAttributes()) {
//            $body .= $this->class->field('snakeAttributes', false, ['visibility' => 'public static']);
//        }
//        if ($model->hasCasts()) {
//            $body .= $this->class->field('casts', $model->getCasts(), ['before' => "\n"]);
//        }
//        if ($model->hasDates()) {
//            $body .= $this->class->field('dates', $model->getDates(), ['before' => "\n"]);
//        }
//        if ($model->hasHidden() && $model->doesNotUseBaseFiles()) {
//            $body .= $this->class->field('hidden', $model->getHidden(), ['before' => "\n"]);
//        }

        if($model->type === 'adapter') {
            if ($model->hasFillable() && $model->doesNotUseBaseFiles()) {
                $body .= $this->class->field('fillable', $model->getFillable(), ['before' => "\n"]);
            }
            $body .= $this->class->field('fillable_relationships', [], ['before' => "\n"]);

            if ($model->hasHints() && $model->usesHints()) {
                $body .= $this->class->field('hints', $model->getHints(), ['before' => "\n"]);
            }
            foreach ($model->getMutations() as $mutation) {
                $body .= $this->class->method($mutation->name(), $mutation->body(), ['before' => "\n"]);
            }

            $content = "parent::__construct(new $model_name(), \$paging);\n";
            $content .= "        \$this->fillable = array_merge(\$this->fillable, \$this->fillable_relationships);";
            $body .= $this->class->method('__construct',$content, [
                'variables' => 'StandardStrategy $paging'
            ]);

            $body .= $this->class->method('filter','$this->filterWithScopes($query, $filters);', [
                'visibility' => 'protected',
                'variables' => '$query, Collection $filters'
            ]);
            foreach ($model->getRelations() as $constraint) {
                $body .= $this->class->method(Str::camel($constraint->name()), $constraint->body(), ['before' => "\n"]);
            }
        }

        if($model->type === 'schema') {
            $body .= $this->class->field('resourceType', $model->getRecordName(), ['before' => "\n"]);

            $body .= $this->class->method('getId','return (string) $resource->getRouteKey();', [
                'variables' => '$resource'
            ]);

            $body .= "    /**";
            $body .= $this->class->annotation("param", $model->getClassName()." \$resource\n    *      the domain record being serialized.");
            $body .= $this->class->annotation("return", "array");
            $body .= "\n    */";
            $body .= $this->class->method('getAttributes', $model->getAttributesSchema(), [
                'variables' => '$resource'
            ]);

            $startRelations = "\$arr = [\n";
            $lower = strtolower($model_name);
            foreach ($model->getRelations() as $constraint) {
                $name = $constraint->name();
                $startRelations .= "            '$name' => [
                self::SHOW_SELF => true,
                self::SHOW_RELATED => true,
                self::SHOW_DATA => isset(\$includeRelationships['$name']),
                self::DATA => function () use (\$$lower) {
                    return \$$lower->$name;
                },
            ],\n";
            }
            $startRelations .= "        ];";
            $startRelations .= "\n        if(method_exists(\$this, 'additionalRelationShips')) { \$arr = array_merge(\$arr, \$this->additionalRelationShips(\$$lower, \$isPrimary, \$includeRelationships)); } \n";
            $startRelations .= "        return \$arr;";
            $body .= $this->class->method('getRelationships', $startRelations, [
                'variables' => "$$lower, \$isPrimary, array \$includeRelationships"
            ]);
        }

        if($model->type === 'validators') {
            $body .= $this->class->field('allowedIncludePaths', $model->getRelationPaths(), ['before' => "\n"]);
            $body .= $this->class->field('allowedSortParameters', $model->getAttributesSorts(), ['before' => "\n"]);
            $body .= $this->class->field('allowedFilteringParameters', $model->getAttributesFiltering(), ['before' => "\n"]);

            $rules = "[\n";
            $queryRules = "[\n";
            $startRelations = "return [\n";
            $lower = strtolower($model_name);
            foreach ($model->getRelations() as $constraint) {
                $name = $constraint->name();
                $ruleType = $constraint->jsonApiRule();
                $startRelations .= "            '$name' => \$$lower->$name,\n";
                $rules .= "            '$name' => [new $ruleType('$name')],\n";
            }
            foreach ($model->getAttributesRules() as $name => $value) {
                if(!$value) continue;
                $rules .= "            '$name' => [";
                $rules .= json_encode($value);
                $rules .= "],\n";
            }
            $startRelations .= "        ];";
            $rules .= "        ]";
            $queryRules .= "        ]";

            $body .= $this->class->method('rules',"return $rules;", [
                'visibility' => 'protected',
                'returns' => "array",
                'variables' => '$record, array $data'
            ]);
            $body .= $this->class->method('queryRules',"return $queryRules;", [
                'visibility' => 'protected',
                'returns' => "array",
                'variables' => ''
            ]);
            $body .= $this->class->method('existingRelationships', 'return [];', [
                'variables' => "$$lower",
                'returns' => "iterable"
            ]);
        }
        // Make sure there not undesired line breaks
        $body = trim($body, "\n");
        return $body;
    }

    /**
     * Returns imports section for model.
     *
     * @param string $baseNamespace base namespace to avoid importing classes from same namespace
     * @param array $usedClasses Array of used in model classes
     *
     * @return string
     */
    private function formatUsedClasses($baseNamespace, $usedClasses) {
        $result = [];
        foreach ($usedClasses as $usedClass) {
            // Do not import classes from same namespace
            $namespacePattern = str_replace('\\', '\\\\', "/{$baseNamespace}\\[a-zA-Z0-9_]*/");
            $baseNamespacePattern = str_replace('\\', '\\\\', "/{$baseNamespace}\\[a-zA-Z0-9_]*\\Base/");
            if (!preg_match($namespacePattern, $usedClass)) {
                $result[] = "use {$usedClass};";
            } elseif(!preg_match($baseNamespacePattern, $usedClass)) {
                $result[] = "use {$usedClass};";
            }
        }
        sort($result);
        return implode("\n", $result);
    }

    /**
     * @param Model $model
     * @param array $custom
     *
     * @return string
     */
    protected function modelPath(Model $model, $custom = []) {
        $modelsDirectory = $this->path(array_merge([$this->config($model->getBlueprint(), 'path'),$model->getClassName(true)], $custom));
        if (!$this->files->isDirectory($modelsDirectory)) {
            $this->files->makeDirectory($modelsDirectory, 0755, true);
        }
        return $this->path([$modelsDirectory, Str::studly($model->type) . '.php']);
    }

    /**
     * @param Model $model
     *
     * @return bool
     */
    public function needsUserFile(Model $model) {
        return !$this->files->exists($this->modelPath($model)) && $model->usesBaseFiles();
    }

    /**
     * @param Model $model
     *
     * @param string $type
     * @throws FileNotFoundException
     */
    protected function createUserFile(Model $model, $type = 'user_model') {
        $file = $this->modelPath($model);
        $template = $this->prepareTemplate($model, $type);
        $template = str_replace('{{namespace}}', $model->getNamespace(), $template);
        $template = str_replace('{{class}}', $model->getTypeClassName(), $template);
//        $template = str_replace('{{imports}}', $this->formatBaseClasses($model), $template);
        $template = str_replace('{{parent}}', $this->getBaseTypeClassName($model), $template);
        $body = '';

        if($model->type === 'schema') {
            $body .= "    /**";
            $body .= $this->class->annotation("param", $model->getClassName()." \$resource\n    *      the domain record being serialized.");
            $body .= $this->class->annotation("return", "array");
            $body .= "\n    */";
            $body .= $this->class->method('getAttributes', $model->getAttributesSchema(), [
                'variables' => '$resource'
            ]);
            $body .= $this->class->method('additionalRelationShips', 'return [];', [
                'variables' => '$product, $isPrimary, array $includeRelationships'
            ]);
        }

        if($model->type === 'validators') {
            $rules = "[\n";
            foreach ($model->getRelations() as $constraint) {
                $name = $constraint->name();
                $ruleType = $constraint->jsonApiRule();
                $rules .= "            '$name' => [new $ruleType('$name')],\n";
            }
            foreach ($model->getAttributesRules() as $name => $value) {
                if(!$value) continue;
                $rules .= "            '$name' => [";
                $rules .= json_encode($value);
                $rules .= "],\n";
            }
            $rules .= "        ]";

            $body .= $this->class->method('rules',"return $rules;", [
                'visibility' => 'protected',
                'returns' => "array",
                'variables' => '$record, array $data'
            ]);
        }
        if($model->type == 'adapter') {
            $body .= $this->class->field('fillable', $model->getFillable(), ['before' => "\n"]);
            $relations = [];
            foreach ($model->getRelations() as $constraint) {
                $name = $constraint->name();
                $relations = array_merge($relations, [$name]);
            }
            $body .= $this->class->field('fillable_relationships', $relations, ['before' => "\n"]);
        }
        $usedClasses = [$this->formatBaseClasses($model)];
        $usedClasses = array_merge($usedClasses, ["App\\Models\\".$model->getClassName()]);
        $usedClasses = array_merge($usedClasses, $this->extractUsedClasses($body));
        $usedClasses = array_unique($usedClasses);
        $usedClassesSection = $this->formatUsedClasses(
            $model->getNamespace(),
            $usedClasses
        );
        $body = trim($body, "\n");
        $template = str_replace('{{body}}', $body, $template);

        $template = str_replace('{{imports}}', $usedClassesSection, $template);
        $this->files->put($file, $template);
    }

    /**
     * @param Model $model
     * @return string
     */
    private function formatBaseClasses(Model $model) {
        return "{$model->getBaseNamespace()}\\{$model->getTypeClassName()} as {$this->getBaseTypeClassName($model)}";
    }

    /**
     * @param Model $model
     * @return string
     */
    private function getBaseClassName(Model $model) {
        return 'Base' . $model->getClassName();
    }

    /**
     * @param Model $model
     * @return string
     */
    private function getBaseTypeClassName(Model $model) {
        return 'Base' . $model->getTypeClassName();
    }

    /**
     * @param Model $model
     *
     * @return string
     */
    protected function userFileBody(Model $model) {
        $body = '';
        if ($model->hasHidden()) {
            $body .= $this->class->field('hidden', $model->getHidden());
        }
        if ($model->hasFillable()) {
            $body .= $this->class->field('fillable', $model->getFillable(), ['before' => "\n"]);
        }
        // Make sure there is not an undesired line break at the end of the class body
        $body = ltrim(rtrim($body, "\n"), "\n");
        return $body;
    }

    /**
     * @param Model $model
     *
     * @return array
     * @todo: Delegate workload to SchemaManager and ModelManager
     *
     */
    public function referencing(Model $model) {
        $references = [];
        // TODO: SchemaManager should do this
        foreach ($this->schemas as $schema) {
            $references = array_merge($references, $schema->referencing($model->getBlueprint()));
        }
        // TODO: ModelManager should do this
        foreach ($references as &$related) {
            $blueprint = $related['blueprint'];
            $related['model'] = $model->getBlueprint()->is($blueprint->schema(), $blueprint->table())
                ? $model
                : $this->makeModel($blueprint->schema(), $blueprint->table(), false);
        }
        return $references;
    }
}
