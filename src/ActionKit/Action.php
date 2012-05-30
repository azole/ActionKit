<?php
/*
    vim:fdm=marker:
 */
namespace ActionKit;
use Exception;
use ActionKit\Column;

/**

    Action schema:

        $this->param('id')
            ->renderAs('HiddenInput');

        $this->param('password')
            ->renderAs('PasswordInput');

    Action Synopsis:

        $a = new Action( .. parameters ... )
        $a->run();

        $rs = $a->result();

    Render Action:

        $a = new UpdatePasswordAction;
        $a->widget('username')->render(array( 
            'class' => 'extra-class'
            'id' => 'field-id'
        ));

        $a->widget('user_type')->render(array( 
            'options' => array( .... )
        ));

        // force widget type
        $a->widget('confirmed','RadioInput')->render(array(
            'false', 'true'
        ));


    In Twig template, we can render action widget:

        {{ 
            a.widget('username').render({ 
                class: 'extra-class',
                id: 'field-id' }) 
        |raw}}

    Render field by Action render method:

        {{ CRUD.Action.render('link',{ 'size': 60 }) | raw }}


    Class:

        function schema() {

            $this->param( 'username' )
                ->label( _('Username') )
                ->useSuggestion();

            $this->param( 'password' )
                ->useValidator();

            $this->param( 'country' )
                ->useCompleter();
        }


        function validatePassword( $value , $args ) 
        {
            return $this->valid( $message );

            # or
            return $this->invalid( $message );
        }

        function suggestUsername( $value , $args ) {
            return;  # not to suggest
            return $this->suggest( "$value is used. use: " , array( ... ) );
        }

        function completeCountry( $value , $args ) {

            ...
        }
    */
abstract class Action 
{
    public $currentUser;
    public $args = array();   // post,get args for action
    public $result; // action result
    protected $params = array(); // parameter column objects

    function __construct( $args = array() , $currentUser = null ) 
    {
        $this->args = $args;
        $this->result = new \ActionKit\Result;
        if( $currentUser )
            $this->currentUser = $currentUser;
        $this->schema();
        $this->result->args( $this->args );
        $this->init();
    }

    function getFileArg( $name )
    {

    }

    protected function validateParam( $name )
    {
        if( $name == '__ajax_request' )
            return;

        if( ! isset($this->params[ $name ] ) ) {
            return;
            // just skip it.
            $this->result->addValidation( $name, array( 'invalid' => "Contains invalid arguments: $name" ));
            return true;
        }

        $param = $this->params[ $name ];
        $ret = (array) $param->validate( @$this->args[$name] );
        if( is_array($ret) ) {
            if( $ret[0] ) {
                # $this->result->addValidation( $name, array( "valid" => $ret[1] ));
            } else {
                $this->result->addValidation( $name, array( 'invalid' => @$ret[1] ));
                return true;
            }
        } else {
            throw new \Exception("Unknown validate return value of $name => " . $this->getName() );
        }
        return false;
    }


    /**
     * Run validates
     *
     * Foreach parameters, validate the parameter through validateParam method.
     *
     * @return bool error flag.
     */
    function runValidate()
    {
        /* it's different behavior when running validation for create,update,delete,
         *
         * for generic action, just traverse all params. */
        $error = false;
        foreach( $this->params as $name => $param ) {
            $hasError = $this->validateParam( $name );
            if( $hasError )
                $error = true;
        }

        if( $error )
            $this->result->error( _('Validation Error') );
        return $error;
    }

    function runPreinit()
    {
        foreach( $this->params as $key => $param ) {
            $param->preinit( $this->args );
        }
    }

    function runInit()
    {
        foreach( $this->params as $key => $param ) {
            $param->init( $this->args );
        }
    }


    public function __invoke() 
    {
        /* run column methods */
        // XXX: merge them all...
        $this->runPreinit();
        $error = $this->runValidate();
        if( $error )
            return false;

        $this->runInit();

        $this->beforeRun();
        $this->run();
        $this->afterRun();
    }


    /* **** value getters **** */
    public function getClass() { 
        return get_class($this);
    }

    public function getName()
    {
        $class = $this->getClass();
        $pos = strpos( $class, '::Action::' );
        return substr( $class , $pos + strlen('::Action::') );
    }

    public function params() 
    {
        return $this->params;
    }

    public function getParam( $field ) 
    {
        return @$this->params[ $field ];
    }

    public function hasParam( $field ) 
    {
        return @$this->params[ $field ] ? true : false; 
    }


    /**
     * Return column widget object
     *
     * @param string $field field name
     *
     * @return FormKit\Widget
     */
    public function widget($field, $widgetClass = null) 
    {
        $param = $this->param($field);
        $widget = $param->createWidget( $widgetClass );
        return $widget;
    }

    public function isAjax()
    {  
        return isset( $_REQUEST['__ajax_request'] );
    }

    public function getCurrentUser() 
    {
        if( $this->currentUser )
            return $this->currentUser;
    }

    public function setCurrentUser( $user ) 
    {
        $this->currentUser = $user;
    }


    public function currentUserCan( $user ) 
    {
        return $this->record->currentUserCan( $this->type , $this->args , $user );
    }

    public function arg( $name ) 
    {
        return @$this->args[ $name ]; 
    }

    public function getArgs() 
    {
        return $this->args; 
    }

    public function getFile( $name )
    {
        return @$_FILES[ $name ];
    }

    public function getFiles() 
    {
        return @$_FILES;
    }


    public function setArg($name,$value) 
    { 
        $this->args[ $name ] = $value ; 
        return $this; 
    }

    public function setArgs($args) 
    { 
        $this->args = $args;
        return $this; 
    }

    public function param( $name , $type = null ) 
    {
        if( isset($this->params[ $name ]) ) {
            return $this->params[ $name ];
        }

        if( $type ) {
            $class = 'ActionKit\\Column\\' . $type;
            return $this->params[ $name ] = new $class( $name , $this );
        }

        // default column
        return $this->params[ $name ] = new Column( $name , $this );
    }

    function schema() 
    {

    }

    function init()
    {

    }


    function addData( $key , $val )
    {
        $this->result->addData( $key , $val );
    }



    function beforeRun() {  }

    function afterRun()  {  }

    /* run */
    function run() 
    {
        return true;
    }


    /**
     * Complete action field 
     *
     * @param string $field field name
     * */
    public function complete( $field ) {
        $param = $this->getParam( $field );
        if( ! $param )
            die( 'action param not found.' );
        $ret = $param->complete();

        if( ! is_array( $ret ) )
            throw new Exception( "Completer doesnt return array. [type,list]\n" );

        // [ type , list ]
        $this->result->completion( $field , $ret[0], $ret[1] );
    }

    public function getResult() 
    {
        return $this->result; 
    }

    public function redirect( $path ) {

        /* for ajax request, we should redirect by json result,
         * for normal post, we should redirect directly. */
        if( $this->isAjax() ) {
            $this->result->redirect( $path );
            return;
        }
        else {
            header( 'Location: ' . $path );
            exit(0);
        }
    }

    public function redirectLater( $path , $secs = 1 )
    {
        if( $this->isAjax() ) {
            // XXX: more support.
            $this->result->redirect( $path );
            return;
        } else {
            header("Refresh: $secs; url=$path");
        }
    }

    public function renderWidget( $name , $type , $attrs = array() )
    {
        $param = $this->getParam( $name );
        return $param->renderWidget( $type, $attrs );
    }

    public function render( $name = null , $attrs = array() ) 
    {
        if( $name ) {
            if( $param = $this->getParam( $name ) )
                return $param->render( $attrs );
            else {
                throw new Exception("parameter $name is not defined.");
            }
        }
        else {
            /* render all */
            $html = '';
            foreach( $this->params as $param ) {
                $html .= $param->render( $attrs );
            }
            return $html;
        }
    }

    /** 
     * Report success
     *
     * @param string $message Success message
     * @param mixed $data
     */
    function success( $message , $data = null ) {
        $this->result->success( $message );
        if( $data )
            $this->result->mergeData( $data );
        return true;
    }

    /**
     * Report error
     *
     * @param string $message Error message
     */
    function error( $message ) {
        $this->result->error( $message );
        return false;
    }

}

