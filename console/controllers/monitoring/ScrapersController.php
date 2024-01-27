<?php namespace console\controllers\monitoring;

use common\contracts\Notifier;
use console\controllers\Controller;

class ScrapersController extends Controller
{
    /** @var Notifier */
    private $notifier;

    public function __construct($id, $module, Notifier $notifier, $config = [])
    {
        $this->notifier = $notifier;
        parent::__construct($id, $module, $config);
    }


}