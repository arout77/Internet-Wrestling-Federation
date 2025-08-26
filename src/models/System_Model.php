<?php

namespace Src\Model;

use \Pimple\Container;

// As of PHP 8.2.0, creating class properties dynamically
// has been deprecated. The following annotation re-enables
// that functionality. All children classes inherit this.
#[\AllowDynamicProperties]

class System_Model
{
    /**
     * The main application container.
     * @var Container
     */
    protected $app;

    /**
     * @param $app
     */
    public function __construct(Container $app)
    {
        // **THE FIX:** Access container services using array syntax `['key']` instead of property syntax `->key`.
        $this->app = $app;
        $this->db = $this->app['db'];
        $this->orm = $this->app['orm'];
        $this->session = $this->app['session'];
        $this->config = $this->app['config'];
        $this->log = $this->app['log'];
        $this->load = $this->app['load'];
    }
}