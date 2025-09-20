<?php
namespace Src\Controller;

use Validate\Enums\Boolean;
use \Src\Kernel as Kernel;

/* Do not allow direct access to this file */
if ( count( get_included_files() ) == 1 )
{
    exit;
}

/*
 * File:    /src/controllers/Base_Controller.php
 * Purpose: Base class from which all controllers extend
 */

class Base_Controller extends Kernel
{
    /**
     * @param $app
     */
    public function __construct( $app )
    {
        parent::__construct( $app );

        if ( session_status() === PHP_SESSION_NONE )
        {
            session_start();
        }

        $prospect = null;
        $record   = ['wins' => 0, 'losses' => 0]; // Default record

        if ( isset( $_SESSION['user_id'] ) )
        {
            $userModel = $this->model( 'User' );
            $prospect  = $userModel->getProspectByUserId( $_SESSION['user_id'] );
            if ( $prospect )
            {
                $record = $userModel->getWrestlerRecord( $prospect['pid'] );
            }
        }

        // Only set Twig global if the template engine is initialized
        if ( isset( $this->template ) && method_exists( $this->template, 'getTwig' ) )
        {
            $this->template->getTwig()->addGlobal( 'prospect', $prospect );
            $this->template->getTwig()->addGlobal( 'record', $record );
        }
    }

    /**
     * @param $_web_class
     * @param $_override_class
     * @return mixed
     */
    public function initOverrideController( $_web_class, $_override_class )
    {
        $this->controller          = $this->route->controller;
        $this->controller_class    = $this->controller . '_Controller';
        $this->controller_filename = ucwords( $this->controller_class ) . '.php';
        $this->action              = $this->route->action;
        $action                    = trim( strtolower( $this->route->action ) );
        $this->parameter           = $this->route->parameter;

        if ( class_exists( $_override_class ) )
        {
            $__instantiate_class = new $_override_class( $this->core );

            if ( !is_subclass_of( $__instantiate_class, $_web_class ) )
            {
                echo $_override_class . ' DOES NOT EXTEND ' . $_web_class;
            }

            if ( method_exists( $__instantiate_class, $action ) )
            {
                call_user_func_array( [$__instantiate_class, $action], $this->parameter );
            }
            else
            {
                if ( $this->debug_mode === Boolean::ON )
                {
                    return $this->redirect( 'error/controller/' . $this->controller . '-' . $this->action );
                }
                return $this->redirect( 'error/not_found' );
            }
        }
        else
        {
            if ( !is_readable( $this->config->setting( 'controllers_path' ) . $this->controller_filename ) )
            {
                if ( $this->debug_mode === Boolean::ON )
                {
                    return $this->redirect( 'error/controller/' . $this->controller . '-' . $this->action );
                }
                return $this->redirect( 'error/not_found' );
            }
        }
    }

    /**
     * @param $_web_class
     * @param $_override_class
     */
    public function initPublicController( $_web_class, $_override_class )
    {
        $this->controller          = $this->route->controller;
        $this->controller_class    = $this->controller . '_Controller';
        $this->controller_filename = ucwords( $this->controller_class ) . '.php';
        $this->parameter           = $this->route->parameter;

        if ( class_exists( $_web_class ) )
        {
            $__instantiate_class = new $_web_class( $this->core );

            if ( method_exists( $__instantiate_class, $this->action ) )
            {
                call_user_func_array( [$__instantiate_class, $this->action], $this->parameter );
            }
            else
            {
                // if ( $this->debug_mode === Boolean::ON )
                // {
                //     return $this->redirect( 'error/controller/' . $this->controller . '-' . $this->action );
                // }
                return $this->redirect( 'error/not_found' );
            }
        }
        else
        {
            if ( !is_readable( $this->config->setting( 'controllers_path' ) . $this->controller_filename ) )
            {
                // if ( $this->debug_mode === Boolean::ON )
                // {
                //     return $this->redirect( 'error/controller/' . $this->controller . '-' . $this->action );
                // }
                return $this->redirect( 'error/not_found' );
            }
        }
    }

    /**
     * @param $model
     * @return object
     */
    public function model( $model ): object
    {
        return $this->load->model( "$model" );
    }

    /**
     * @return mixed
     */
    final public function parse()
    {
        # Define child controller extending this class
        $this->controller = $this->route->controller ?? $this->config->setting( 'default_controller' );
        # The class name contained inside child controller
        $this->controller_class = $this->controller . '_Controller';
        # File name of child controller
        $this->controller_filename = ucwords( $this->controller_class ) . '.php';
        # Action being requested from child controller
        $this->action = $this->route->action ?? 'index';
        $action       = trim( strtolower( $this->action ) );
        # URL parameters
        $this->parameter = $this->route->parameter;
        # Public and Override classes
        $_web_class      = "\App\Controller\\" . ucwords( $this->controller_class );
        $_override_class = "\App\ControllerOverride\\" . ucwords( $this->controller_class );

        # First search for requested controller file in override directory
        if ( is_readable( PUBLIC_OVERRIDE_PATH . 'controllers/' . $this->controller_filename ) )
        {
            return self::initOverrideController( $_web_class, $_override_class );
        }

        if ( is_readable( $this->config->setting( 'controllers_path' ) . $this->controller_filename ) )
        {
            return self::initPublicController( $_web_class, $_override_class );
        }

        # Controller file does not exist, or does not have read permissions
        // if ( $this->debug_mode === 'ON' )
        // {
        //     return $this->redirect( 'error/controller/' . $this->controller );
        // }

        return $this->redirect( 'error/not_found/' );
    }

    /**
     * @param $middleware
     * @return mixed
     */
    public function plugin( $middleware )
    {
        # Load a plugin middleware
        return $this->plugin["$middleware"];
    }

    /**
     * @param $url
     */
    public function redirect( $url )
    {
        if ( $url === 'http_referer' )
        {
            return header( 'Location: ' . $_SERVER['HTTP_REFERER'] );
        }
        return header( 'Location: ' . SITE_URL . $url );
    }

    /**
     * @return mixed
     */
    public function session()
    {
        return $this->plugin( 'session' );
    }

    /**
     * @param $headers
     */
    public function set_headers( $headers )
    {
        if ( !is_array( $headers ) )
        {
            return header( "$headers" );
        }

        foreach ( $headers as $header )
        {
            header( "$header" );
        }
    }
}
