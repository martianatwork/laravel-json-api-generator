<?php
/**
 * Created by Cristian.
 * Date: 12/10/16 12:09 AM.
 */

namespace MartianAtWork\Database\Eloquent;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Http\Request;

class WhoDidIt
{
    /**
     * @var Request
     */
    protected $request;

    /**
     * Blamable constructor.
     *
     * @param Request $request
     */
    public function __construct(Request $request) {
        $this->request = $request;
    }

    /**
     * @param string $event
     * @param Eloquent $model
     */
    public function creating($event, Eloquent $model) {
        $model->created_by = $this->doer();
    }

    /**
     * @return mixed|string
     */
    protected function doer() {
        if (app()->runningInConsole()) {
            return 'CLI';
        }
        return $this->authenticated() ? $this->userId() : '????';
    }

    /**
     * @return mixed
     */
    protected function authenticated() {
        return $this->request->user();
    }

    /**
     * @return mixed
     */
    protected function userId() {
        return $this->authenticated()->id;
    }

    /**
     * @param string $event
     * @param Eloquent $model
     */
    public function updating($event, Eloquent $model) {
        $model->udpated_by = $this->doer();
    }
}
