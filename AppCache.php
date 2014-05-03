<?php

namespace mdm\tools;

use \Yii;
use yii\web\View;
use yii\helpers\Url;
use yii\web\Application;

/**
 * Description of AppCache
 *
 * @author MDMunir
 */
class AppCache extends \yii\base\Behavior
{
    const CACHE_KEY = 'manifest';

    private $_cache = false;
    private $_manifest_id;
    public $template_file = '@mdm/tools/manifest.php';
    public $route = '/site/manifest';
    public $extra_caches = [];
    public $uniqueForClient = true;
    public $uniqueForUser = true;

    public function events()
    {
        return[
            Application::EVENT_BEFORE_ACTION => 'beforeAction'
        ];
    }

    /**
     * 
     * @param \yii\base\ActionEvent $event
     */
    public function beforeAction($event)
    {
        $this->_manifest_id = md5($event->action->uniqueId);
        $view = $event->action->controller->getView();
        $view->on(View::EVENT_END_PAGE, [$this, 'createManifest']);
        $view->on(View::EVENT_END_BODY, [$this, 'swapCache']);
    }

    public function swapCache($event)
    {
        if ($this->_cache === false) {
            return;
        }
        $js = "
if (window.applicationCache) {
	window.applicationCache.update();
	window.applicationCache.addEventListener('updateready', function(e) {
		if (window.applicationCache.status == window.applicationCache.UPDATEREADY) {
			window.applicationCache.swapCache();
			//window.location.reload();
		}
	}, false);
}
";
        $event->sender->registerJs($js, View::POS_BEGIN);
    }

    public function createManifest($event)
    {
        if ($this->_cache === false) {
            return;
        }
        try {
            $cache = Yii::$app->cache;
            $key = [self::CACHE_KEY, $this->_manifest_id];
            if ($cache === null or $cache->get($key) === false or YII_ENV == 'dev') {
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
                    $cache->set($key, [
                        'template_file' => $this->template_file,
                        'caches' => $caches,
                        'uniqueForClient' => $this->uniqueForClient,
                        'uniqueForUser' => $this->uniqueForUser,
                    ]);
                }
            }
        } catch (\Exception $exc) {
            throw $exc;
        }
    }

    public function cacheApp()
    {
        $this->_cache = true;
    }

    public function getManifestFile()
    {
        return $this->_cache ? Url::toRoute([$this->route, 'id' => $this->_manifest_id], true) : null;
    }
}