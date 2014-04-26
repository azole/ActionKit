<?php
namespace ActionKit;
use UniversalCache;
use Twig_Loader_Filesystem;
use Twig_Environment;
use ReflectionClass;
use ClassTemplate\ClassTemplate;

/**
 * Action Generator Synopsis
 *
 *    $generator = new ActionGenerator(array(
 *          'cache' => true,                 // this enables apc cache.
 *
 *
 *          // currently we only use APC
 *          'cache_dir' => 'phifty/cache',
 *          'template_dirs' => array( 'Resource/Templates' )
 *    ));
 *    $classFile = $generator->generate( 'Plugin\Action\TargetClassName', 'CreateRecordAction.template' , array( ));
 *    require $classFile;
 *
 *
 * Depends on Twig template engine
 *
 */
class ActionGenerator
{

    public $cacheDir;

    public $templateDirs = array();

    public $templates = array();

    public function __construct( $options = array() )
    {
        if ( isset($options['cache_dir']) ) {
            $this->cacheDir = $options['cache_dir'];
        } else {
            $this->cacheDir = __DIR__ . DIRECTORY_SEPARATOR . 'Cache';
            if ( ! file_exists($this->cacheDir) ) {
                mkdir($this->cacheDir, 0755, true);
            }
        }
    }

    public function addTemplateDir($path)
    {
        $this->templateDirs[] = $path;
    }


    /**
     * This method generates class code based on the template file from Twig
     *
     * XXX: is going to be deprecated.
     */
    // $template = $gen->generate($class, $actionArgs['template'], $actionArgs['variables']);
    // $variables['base_class']
    // $variables['record_class']
    public function generate($targetClassName, $template = null, $variables = array())
    {
        $parts = explode("\\",$targetClassName);
        $variables['target'] = array();
        $variables['target']['classname'] = array_pop($parts);
        $variables['target']['namespace'] = join("\\", $parts);
        $twig = $this->getTwig();
        return $twig->render($template, $variables);
    }


    /**
     * The new generate method to generate action class
     *
     * @param string $targetClassName
     * @param array  $options
     *
     * @synopsis
     *
     *    $template = $g->generate2('ProductBundle\\Action\\SortProductImage', [ 
     *              'extends' => 'SortablePlugin\\Action\\SortRecordAction',
     *              'constants' => [
     *                  ...
     *              ],
     *              'properties' => [
     *                  'recordClass' => 'ProductBundle\\Model\\ProductImage',
     *                  'fields' => '....',
     *              ],
     *    ]);
     *    $template->addMethod(...); // extra operations
     *
     *
     */
    public function generate2($targetClassName, $options = array() )
    {
        $classTemplate = new ClassTemplate($targetClassName);
        // general use statement
        $classTemplate->useClass('\\ActionKit\\Action');
        $classTemplate->useClass('\\ActionKit\\RecordAction\\BaseRecordAction');
        /*
        $classTemplate->useClass('\\ActionKit\\RecordAction\\CreateRecordAction');
        $classTemplate->useClass('\\ActionKit\\RecordAction\\UpdateRecordAction');
        $classTemplate->useClass('\\ActionKit\\RecordAction\\DeleteRecordAction');
        $classTemplate->useClass('\\ActionKit\\RecordAction\\BulkDeleteRecordAction');
        $classTemplate->useClass('\\ActionKit\\RecordAction\\BulkCreateRecordAction');
         */

        if ( isset($options['extends']) ) {
            $classTemplate->extendClass($options['extends']);
        }
        if ( isset($options['properties']) ) {
            foreach( $options['properties'] as $name => $value ) {
                $classTemplate->addProperty($name, $value);
            }
        }
        if ( isset($options['constants']) ) {
            foreach( $options['constants'] as $name => $value ) {
                $classTemplate->addConst($name, $value);
            }
        }
        return $classTemplate;
    }



    public function getTwigLoader() {

        static $loader;
        if ( $loader ) {
            return $loader;
        }
        // add ActionKit built-in template path
        $loader = new Twig_Loader_Filesystem($this->templateDirs);
        $loader->addPath( __DIR__ . DIRECTORY_SEPARATOR . 'Templates', "ActionKit" );
        return $loader;
    }

    public function getTwig()
    {
        static $twig;
        if ( $twig ) {
            return $twig;
        }
        $loader = $this->getTwigLoader();
        $env = new Twig_Environment($loader, array(
            'cache' => $this->cacheDir ? $this->cacheDir : false,
        ));
        return $env;
    }



    /**
     * Given a model class name, split out the namespace and the model name.
     *
     * @param string $modelClass full-qualified model class name
     * @param string $type action type
     *
     *
     *  $g->generateClassCode( 'App\Model\User', 'Create' ); // generates App\Action\CreateUser
     *
     */
    public function generateClassCode( $modelClass , $type )
    {
        list($modelNs, $modelName) = explode('\\Model\\', $modelClass);
        return $this->generateClassCodeWithNamespace($modelNs, $modelName, $type);
    }

    /**
     * Generate record action class dynamically.
     *
     * generate( 'PluginName' , 'News' , 'Create' );
     * will generate:
     * PluginName\Action\CreateNews
     *
     * @param string $ns
     * @param string $modelName
     * @param string $type  RecordAction Type
     *
     * @return ClassTemplate
     */
    public function generateClassCodeWithNamespace($ns, $modelName , $type)
    {
        $ns = ltrim($ns,'\\');
        // here we translate App\Model\Book to App\Action\CreateBook or something
        $actionFullClass = $ns . '\\Action\\' . $type . $modelName;
        $recordClass  = $ns . '\\Model\\' . $modelName;
        $baseAction   = $type . 'RecordAction';
        return $this->generate2($actionFullClass, [ 
            'extends' => "\\ActionKit\\RecordAction\\$baseAction",
            'properties' => [ 
                'recordClass' => $recordClass,
            ],
        ]);
    }


    /**
     * Generate a generic action class code with an empty schema, run methods
     *
     * @param string $namespaceName the parent namespace of the 'Action' namespace.
     * @param string $actionName    the action class name (short class name)
     * @return ClassTemplate
     */
    public function generateActionClassCode($namespaceName,$actionName)
    {
        $classTemplate = $this->generate2("$namespaceName\\Action\\$actionName", [ 
            'extends' => 'Action',
        ]);
        $classTemplate->addMethod('public','schema', [] , '');
        $classTemplate->addMethod('public','run', [] , 'return $this->success("Success!");');
        return $classTemplate;
    }

}
