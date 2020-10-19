<?php

namespace MartianAtWork\Coders\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
use MartianAtWork\Coders\Model\Factory;

class CodeModelsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'code:json-api
                            {--s|schema= : The name of the MySQL database}
                            {--c|connection= : The name of the connection}
                            {--t|table= : The name of the table}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Parse connection schema into models';

    /**
     * @var Factory
     */
    protected $models;

    /**
     * @var Repository
     */
    protected $config;

    /**
     * Create a new command instance.
     *
     * @param Factory $models
     * @param Repository $config
     */
    public function __construct(Factory $models, Repository $config) {
        parent::__construct();
        $this->models = $models;
        $this->config = $config;
    }

    /**
     * Execute the console command.
     */
    public function handle() {
        $connection = $this->getConnection();
        $schema = $this->getSchema($connection);
        $table = $this->getTable();
        // Check whether we just need to generate one table
        if ($table) {
            $this->models->on($connection, $schema)->create($schema, $table);
            $this->info("Check out your models for $table");
        } // Otherwise map the whole database
        else {
            $this->models->on($connection, $schema)->map($schema);
            $this->info("Check out your models for $schema");
        }
    }

    /**
     * @return string
     */
    protected function getConnection() {
        return $this->option('connection') ?: $this->config->get('database.default');
    }

    /**
     * @param $connection
     *
     * @return string
     */
    protected function getSchema($connection) {
        return $this->option('schema') ?: $this->config->get("database.connections.$connection.database");
    }

    /**
     * @return string
     */
    protected function getTable() {
        return $this->option('table');
    }
}
