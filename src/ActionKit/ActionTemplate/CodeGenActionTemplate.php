<?php
namespace ActionKit\ActionTemplate;
use ActionKit\ActionRunner;
use ActionKit\GeneratedAction;
use ActionKit\Exception\RequiredConfigKeyException;
use Exception;
use ClassTemplate\TemplateClassFile;

/**
 *  CodeGen-Based Action Template Synopsis
 *
 *      $actionTemplate = new CodeGenActionTemplate();
 *      $runner = new ActionKit\ActionRunner;
 *      $actionTemplate->register($runner, 'CodeGenActionTemplate', array(
 *          'namespace' => 'test2',
 *          'model' => 'test2Model',   // model's name
 *          'types' => array('Create','Update','Delete','BulkDelete')
 *      ));
 *
 *      $className = 'test2\Action\UpdatetestModel';
 *      $generatedAction = $actionTemplate->generate($className, [
 *          'extends' => "\\ActionKit\\RecordAction\\CreateRecordAction",
 *          'properties' => [
 *              'recordClass' => "test2\\Model\\testModel",
 *          ],
 *      ]);
 *
 *      $generatedAction->requireAt($cacheCodePath);
 *
 */
class CodeGenActionTemplate implements ActionTemplate
{

    /**
     * @synopsis
     *
     *    $template->register($runner, array(
     *        'namespace' => 'test',
     *        'model' => 'testModel',   // model's name
     *        'types' => array('Create','Update','Delete','BulkDelete')
     *    ));
     */
    public function register(ActionRunner $runner, $asTemplate, array $options = array())
    {
        //$ns , $modelName , $types
        if (!isset($options['namespace'])) {
            throw new RequiredConfigKeyException('namespace is not defined.');
        }
        $ns = $options['namespace'];

        if (!isset($options['model'])) {
            throw new RequiredConfigKeyException('model name is not defined.');
        }
        $modelName = $options['model'];

        if (! isset($options['types'])) {
            throw new RequiredConfigKeyException('types is not defined.');
        }
        $types = $options['types'];

        foreach ( (array) $types as $type ) {
            $class = $ns . '\\Action\\' . $type . $modelName;
            $runner->register( $class, $asTemplate, [
                'extends' => "\\ActionKit\\RecordAction\\{$type}RecordAction",
                'properties' => [
                    'recordClass' => "$ns\\Model\\$modelName",
                ],
            ]);
        }
    }
    
    /**
     * @synopsis
     *
     *    $generatedAction = $template->generate('test\Action\UpdatetestModel',
     *       [
     *           'extends' => "\\ActionKit\\RecordAction\\CreateRecordAction",  
     *           'properties' => [
     *               'recordClass' => "test\\testModel\\$modelName",    // $ns\\Model\\$modelName
     *           ],
     *           'getTemplateClass' => true  // return TemplateClassFile directly
     *       ]
     *    );
     */
    public function generate($actionClass, array $options = array())
    {
        $templateClassFile = new TemplateClassFile($actionClass);

        // General use statement
        $templateClassFile->useClass('\\ActionKit\\Action');
        $templateClassFile->useClass('\\ActionKit\\RecordAction\\BaseRecordAction');

        if ( isset($options['extends']) ) {
            $templateClassFile->extendClass($options['extends']);
        }
        if ( isset($options['properties']) ) {
            foreach( $options['properties'] as $name => $value ) {
                $templateClassFile->addProperty($name, $value);
            }
        }
        if ( isset($options['constants']) ) {
            foreach( $options['constants'] as $name => $value ) {
                $templateClassFile->addConst($name, $value);
            }
        }

        $code = $templateClassFile->render();
        return new GeneratedAction($actionClass, $code, $templateClassFile);
    }
}
