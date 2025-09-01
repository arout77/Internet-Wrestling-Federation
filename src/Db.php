<?php

namespace Src;

use \PDO;
use \Pimple\Container;
use \Src\Enums\Database_Types;
use \Src\Enums\Error_Reports;
use \Src\Error;

class Db
{
    /**
     * @var mixed
     */
    public $db;
    /**
     * @var mixed
     */
    private $app;

    /**
     * @param Container $app
     */
    public function __construct( Container $app )
    {
        $this->app = $app;

        $db_type = $this->app['config']->setting( 'db_type' );
        $db_host = $this->app['config']->setting( 'db_host' );
        $db_port = $this->app['config']->setting( 'db_port' );
        $db_name = $this->app['config']->setting( 'db_name' );
        $db_user = $this->app['config']->setting( 'db_user' );
        $db_pass = $this->app['config']->setting( 'db_pass' );

        if ( !empty( $db_port ) )
        {
            $db_port = ";port={$db_port}";
        }

        $options = [
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        ];

        try {
            $this->db = new PDO( "{$db_type}:host={$db_host}{$db_port};dbname={$db_name};charset=utf8", $db_user, $db_pass, $options );
        }
        catch ( \PDOException $e )
        {
            $error = new Error( $this->app );
            $error->database_connection_error( $e->getMessage(), Error_Reports::E_DATABASE_CONNECTION );
        }
    }
}
