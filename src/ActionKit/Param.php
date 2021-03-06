<?php
namespace ActionKit;
use CascadingAttribute;
use FormKit;
use ActionKit\Messages;

class Param extends CascadingAttribute
{
    /**
     * @var ActionKit\Action action object referenece
     * */
    public $action;

    /**
     * @var string action param name
     */
    public $name;

    /**
     * @var string action param type
     */
    public $type;

    /**
     * @var boolean is a required column ?
     */
    public $required;

    /* current value ? */
    public $value;

    /* valid values */
    public $validValues;

    /* valid pair values */
    public $validPairs;

    public $optionValues;

    /* default value */
    public $default;

    /* is immutable ? */
    public $immutable;

    /* refer class *? */
    public $refer;

    /* default render Widget */
    public $widgetClass = 'TextInput';

    /* default widget attributes */
    public $widgetAttributes = array();

    /* default widget namespace */
    public $widgetNamespace = 'FormKit\\Widget';

    public $validator;

    public function __construct( $name , $action = null )
    {
        $this->name = $name;
        $this->action = $action;

        $this->setAttributeType('immutable', static::ATTR_FLAG);
        $this->setAttributeType('required',  static::ATTR_FLAG);
        $this->build();
    }

    public function build()
    {

    }

    /**
     * We dont save any value here,
     * The value's should be passed from outside.
     *
     * Supported validations:
     *   * required
     *
     * @param mixed $value
     *
     * @return array|true Returns error with message or true
     */
    public function validate( $value )
    {
        /* if it's file type , should read from $_FILES , not from the args of action */
        // TODO: note, we should do this validation in File Param or Image Param
        if ($this->paramType === 'file') {
            if ( $this->required
                && ( ! isset($_FILES[ $this->name ]['tmp_name']) && ! isset($_REQUEST[$this->name]) )
            ) {
                return array(false, __( Messages::get('file.required') , $this->getLabel()  ) );
            }
        } else {
            if ( $this->required 
                && ( ! isset($_REQUEST[ $this->name ])
                        || ! $_REQUEST[$this->name] 
                    )
                && ! $this->default ) 
            {
                return array(false, __( Messages::get('param.required') , $this->getLabel()  ) );
            }
        }
        if ($this->validator) {
            return call_user_func($this->validator,$value);
        }
        return true;
    }

    public function preinit( & $args )
    {

    }

    public function init( & $args )
    {

    }

    public function getLabel()
    {
        if ( $this->label ) {
            return _($this->label);
        }
        return ucfirst($this->name);
    }

    public function getDefaultValue()
    {
        if ( is_callable($this->default) ) {
            return call_user_func($this->default);
        }

        return $this->default;
    }

    /**************************
     * Widget related methods
     **************************/

    /**
     * Render action column as {Type}Widget, with extra options/attributes
     *
     *     $this->column('created_on')
     *         ->renderAs('DateInput', array( 'format' => 'yy-mm-dd' ))
     *
     * @param string $type       Widget type
     * @param array  $attributes
     *
     * @return self
     */
    public function renderAs( $type , $attributes = null )
    {
        $this->widgetClass = $type;
        if ($attributes) {
            $this->widgetAttributes = array_merge( $this->widgetAttributes, $attributes );
        }

        return $this;
    }

    /**
     * Render current parameter column to HTML
     *
     * @param  array|null $attributes
     * @return string
     */
    public function render($attributes = null)
    {
        return $this->createWidget( null , $attributes )
            ->render();
    }

    public function getValidValues()
    {
        if ( is_callable($this->validValues) ) {
            return call_user_func($this->validValues);
        }
        return $this->validValues;
    }

    public function getOptionValues()
    {
        if ( is_callable($this->optionValues) ) {
            return call_user_func($this->optionValues);
        }
        return $this->optionValues;
    }

    public function createHintWidget($widgetClass = null , $attributes = array() )
    {
        if ($this->hint) {
            $class = $widgetClass ?: 'FormKit\\Element\\Div';
            $widget = new $class( $attributes );
            $widget->append($this->hint);

            return $widget;
        }
    }

    public function createLabelWidget($widgetClass = null , $attributes = array() )
    {
        $class = $widgetClass ?: 'FormKit\\Widget\\Label';
        return new $class( $this->getLabel() );
    }


    public function getRenderableCurrentValue()
    {
        // XXX: we should handle "false", "true", and "NULL"
        return $this->value instanceof \LazyRecord\BaseModel ? $this->value->dataKeyValue() : $this->value;
    }



    /**
     * A simple widget factory for Action Param
     *
     * @param  string                    $widgetClass Widget Class.
     * @param  array                     $attributes  Widget attributes.
     * @return FormKit\Widget\BaseWidget
     */
    public function createWidget( $widgetClass = null , $attributes = array() )
    {
        $class = $widgetClass ?: $this->widgetClass;

        // convert attributes into widget style attributes
        $newAttributes = array();
        $newAttributes['label'] = $this->getLabel();

        if ($this->validValues) {
            $newAttributes['options'] = $this->getValidValues();
        } elseif ($this->optionValues) {
            $newAttributes['options'] = $this->getOptionValues();
        }

        if ($this->immutable) {
            $newAttributes['readonly'] = true;
        }

        // for inputs (except password input),
        // we should render the value (or default value)
        if ( false === stripos( $class , 'Password' ) ) {
            // The Param class should respect the data type
            if ($this->value !== NULL) {
                $newAttributes['value'] = $this->getRenderableCurrentValue();
            } elseif ($this->default) {
                $newAttributes['value'] = $this->getDefaultValue();
            }
        }

        if ($this->placeholder) {
            $newAttributes['placeholder'] = $this->placeholder;
        }
        if ($this->hint) {
            $newAttributes['hint'] = $this->hint;
        }

        if ($this->immutable) {
            $newAttributes['readonly'] = true;
        }

        // merge override attributes
        if ($this->widgetAttributes) {
            $newAttributes = array_merge( $newAttributes , $this->widgetAttributes );
        }
        if ($attributes) {
            $newAttributes = array_merge( $newAttributes , $attributes );
        }

        // if it's not a full-qualified class name
        // we should concat class name with default widget namespace
        if ('+' == $class[0]) {
            $class = substr($class,1);
        } else {
            $class = $this->widgetNamespace . '\\' . $class;
        }

        // create new widget object.
        return new $class($this->name , $newAttributes);
    }
}
