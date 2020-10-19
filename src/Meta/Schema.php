<?php
/**
 * Created by Cristian.
 * Date: 02/10/16 07:56 PM.
 */

namespace MartianAtWork\Meta;
use Illuminate\Database\ConnectionInterface;

/**
 * Created by Cristian.
 * Date: 18/09/16 06:50 PM.
 */
interface Schema
{
    /**
     * @return ConnectionInterface
     */
    public function connection();

    /**
     * @return string
     */
    public function schema();

    /**
     * @return Blueprint[]
     */
    public function tables();

    /**
     * @param string $table
     *
     * @return bool
     */
    public function has($table);

    /**
     * @param string $table
     *
     * @return Blueprint
     */
    public function table($table);

    /**
     * @param Blueprint $table
     *
     * @return array
     */
    public function referencing(Blueprint $table);
}
