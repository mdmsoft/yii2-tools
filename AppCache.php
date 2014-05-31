<?php

namespace mdm\tools;

use \Yii;
use yii\web\View;
use yii\helpers\FileHelper;

/**
 * Description of AppCache
 *
 * @author MDMunir
 */
class AppCache extends \yii\base\ActionFilter
{
    public $extra_caches = [];
    public $actions = [];
    private $_manifest_file;

    /**
     * 
     * @param \yii\base\Action $action
     */
    public function beforeAction($action)
    {
        $view = $action->controller->view;
        $id = $action->uniqueId;
        $view->on(View::EVENT_END_PAGE, [$this, 'createManifest'], $id);
        $view->on(View::EVENT_END_BODY, [$this, 'swapCache']);
        $this->_manifest_file = static::getFileName($id, true);
        return true;
    }

    /**
     * 
     * @param \yii\base\Action $action
     */
    public function afterAction($action, $result)
    {
        $view = $action->controller->view;
        $view->off(View::EVENT_END_PAGE, [$this, 'createManifest']);
        $view->off(View::EVENT_END_BODY, [$this, 'swapCache']);
        $this->_manifest_file = null;
        $id = $action->uniqueId;
        if (!file_exists(static::getFileName($id))) {
            static::invalidate($id);
        }
        return $result;
    }
    
    protected function isActive($action)
    {
        return in_array($action->id, $this->actions, true);
    }

    private static function getFileName($id, $url = false)
    {
        $key = static::buildKey($id);
        return Yii::getAlias(($url ? '@web' : '@webroot') . "/assets/manifest/{$key}.manifest");
    }

    private static function buildKey($id)
    {
        return md5(serialize([
            __CLASS__,
            $id
        ]));
    }

    public static function invalidate($id)
    {
        $key = static::buildKey($id);
        $cache = Yii::$app->cache;
        if ($cache !== null && ($caches = $cache->get($key)) !== false) {
            $view = new View();
            $file = Yii::getAlias('@mdm/tools/manifest.php');
            $manifest = $view->renderPhpFile($file, ['caches' => $caches]);
            $filename = static::getFileName($id);
            FileHelper::createDirectory(dirname($filename));
            file_put_contents($filename, $manifest);
        }
    }

    public function swapCache($event)
    {
        $js = <<<JS
if (window.applicationCache) {
	window.applicationCache.update();
	window.applicationCache.addEventListener('updateready', function(e) {
		if (window.applicationCache.status == window.applicationCache.UPDATEREADY) {
			window.applicationCache.swapCache();
			//window.location.reload();
		}
	}, false);
}
JS;
        $event->sender->registerJs($js, View::POS_BEGIN);
    }

    /**
     * 
     * @param \yii\base\Event $event
     * @throws \Exception
     */
    public function createManifest($event)
    {
        try {
            $cache = Yii::$app->cache;
            $key = static::buildKey($event->data);
            if ($cache !== null && (( $caches = $cache->get($key)) === false or YII_ENV == 'dev')) {
                $view = $event->sender;
                $html = '<html>';
                foreach ($view->jsFiles as $jsFiles) {
                    $html.="\n" . implode("\n", $jsFiles);
                }
                $html.="\n" . implode("\n", $view->cssFiles);
                $html.="\n</html>";

                $caches = [];
                $dom = new \DOMDocument();
                $dom->loadHTML($html);
                foreach ($dom->getElementsByTagName('script') as $script) {
                    $caches[] = $script->getAttribute('src');
                }
                foreach ($dom->getElementsByTagName('link') as $style) {
                    $caches[] = $style->getAttribute('href');
                }
                $caches = array_merge($caches, $this->extra_caches);

                if ($cache !== null) {
                    $cache->set($key, $caches);
                }
            }
        } catch (\Exception $exc) {
            throw $exc;
        }
    }

    public function getManifestFile()
    {
        return $this->_manifest_file;
    }
}