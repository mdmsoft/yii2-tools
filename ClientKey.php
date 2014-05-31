<?php

namespace mdm\tools;

use yii\web\Cookie;
use Yii;

/**
 * Description of ClientKey
 *
 * @property string $clientUniqueid
 * 
 * @author MDMunir <misbahuldmunir@gmail.com>
 */
class ClientKey extends \yii\base\Behavior
{
    const COOKIE_KEY = '_client_uniqueid';

    private $_clientUniqueid;

    public $expire = 31536000; // default 1 year

    public function init()
    {
        parent::init();
        $cookie = Yii::$app->getRequest()->cookies->get(self::COOKIE_KEY);
        if ($cookie) {
            $this->_clientUniqueid = $cookie->value;
        } else {
            $str = microtime(true);
            if (($session = Yii::$app->getSession()) !== null) {
                $str .= $session->id;
            }
            $this->_clientUniqueid = md5($str . ':' . microtime(true));
            $cookie = new Cookie();
            $cookie->name = self::COOKIE_KEY;
            $cookie->value = $this->_clientUniqueid;
        }
        $cookie->expire = time() + $this->expire;
        Yii::$app->getResponse()->cookies->add($cookie);
    }

    public function getClientUniqueid()
    {
        return $this->_clientUniqueid;
    }

    public function canGetProperty($name, $checkVars = true)
    {
        return stripos($name, 'client') === 0 || parent::canGetProperty($name, $checkVars);
    }

    public function canSetProperty($name, $checkVars = true)
    {
        return stripos($name, 'client') === 0 || parent::canSetProperty($name, $checkVars);
    }

    public function __get($name)
    {
        if (stripos($name, 'client') === 0 && strcasecmp($name, 'clientUniqueid')!=0) {
            return $this->getClientProperty(strtolower($name));
        } else {
            return parent::__get($name);
        }
    }

    public function __set($name, $value)
    {
        if (stripos($name, 'client') === 0 && strcasecmp($name, 'clientUniqueid')!=0) {
            $this->setClientProperty(strtolower($name), $value);
        } else {
            return parent::__get($name);
        }
    }

    private function buildKey($key)
    {
        return [
            __CLASS__,
            $this->_clientUniqueid,
            $key
        ];
    }

    private function setClientProperty($key, $value)
    {
        if (($cache=  Yii::$app->cache) !== null) {
            $cache->set($this->buildKey($key), $value);
        }
    }

    private function getClientProperty($key)
    {
        if (($cache=  Yii::$app->cache) !== null) {
            $result = $cache->get($this->buildKey($key));
            return $result === false ? null : $result;
        }
        return null;
    }
}