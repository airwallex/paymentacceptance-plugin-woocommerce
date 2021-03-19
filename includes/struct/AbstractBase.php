<?php
namespace Airwallex\Struct;


abstract class AbstractBase{
    public function __construct($dataArray = null)
    {
        if(is_array($dataArray)){
            $this->setFromArray($dataArray);
        }
        return $this;
    }

    public function setFromArray($dataArray){
        foreach($dataArray as $fieldName=>$fieldValue){
            $fieldName = str_replace('_', '', ucwords($fieldName, '_'));
            $methodName = 'set'.ucfirst($fieldName);
            /*if(is_array($fieldValue)) {
                $className = __NAMESPACE__.'\\'.ucfirst($fieldName);
                if(class_exists($className)){
                    $fieldValue = new $className($fieldValue);
                }
            }*/
            if (method_exists($this, $methodName)) {
                $this->{$methodName}($fieldValue);
            }else{
                //throw new \Exception('field not found: '.$fieldName.' in '.get_called_class());
            }
        }
    }

    /**
     * @return array
     */
    public function toArray(){
        $return = [];
        foreach(array_keys(get_object_vars($this)) as $property){
            if(isset($this->{$property})){
                if(is_object($this->{$property}) && method_exists($this->{$property}, 'toArray')){
                    $value = $this->{$property}->toArray();
                }else{
                    $value = $this->{$property};
                }
                $return[$property] = $value;
            }
        }
        return $return;
    }
}