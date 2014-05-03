<?php

namespace mdm\tools;

use yii\base\Behavior;
use yii\db\BaseActiveRecord;
use yii\helpers\Inflector;
use \ReflectionClass;
use \Yii;
use yii\mongodb\Collection;
use yii\helpers\ArrayHelper;

/**
 * Description of Logger
 *
 * @author MDMunir
 */
class Logger extends Behavior
{
    /**
     *
     * @var Collection[]
     */
    private static $_collection = [];
    private static $_user_id;
    public $logParams = [];
    public $attributes = [];
    public $collectionName;

    public function init()
    {
        if ($this->collectionName === null) {
            $reflector = new ReflectionClass($this->owner);
            $this->collectionName = Inflector::underscore($reflector->getShortName());
        }
        if (!isset(self::$_collection[$this->collectionName])) {
            self::$_collection[$this->collectionName] = Yii::$app->mongodb->getCollection($this->collectionName);
        }
        if (self::$_user_id === null) {
            $user = Yii::$app->user;
            self::$_user_id = $user->getIsGuest() ? 0 : $user->getId();
        }
    }

    public function events()
    {
        return[
            BaseActiveRecord::EVENT_AFTER_INSERT => 'insertLog',
            BaseActiveRecord::EVENT_AFTER_UPDATE => 'insertLog',
        ];
    }

    public function insertLog($event)
    {
        $model = $this->owner;
        $logs = ArrayHelper::merge([
                'log_time1' => new \MongoDate(),
                'log_time2' => time(),
                'log_by' => self::$_user_id,
                ], $this->logParams);
        $data = [];
        foreach ($this->attributes as $attribute) {
            if ($model->hasAttribute($attribute)) {
                $data[$attribute] = $model->{$attribute};
            } elseif (isset($logs[$attribute]) || array_key_exists($attribute, $logs)) {
                $data[$attribute] = $logs[$attribute];
            }
        }
        try {
            self::$_collection[$this->collectionName]->insert($data);
        } catch (\Exception $exc) {
            
        }
    }
    
}