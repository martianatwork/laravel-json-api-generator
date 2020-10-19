<?php
/**
 * Created by Cristian.
 * Date: 02/10/16 11:06 PM.
 */

namespace MartianAtWork\Meta;
use Illuminate\Support\Fluent;

interface Column
{
    /**
     * @return Fluent
     */
    public function normalize();
}
