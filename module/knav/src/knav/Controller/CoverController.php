<?php

namespace knav\Controller;
use VuFind\Cover\CachingProxy, knav\Cover\Loader;

class CoverController extends \VuFind\Controller\CoverController
{
   
    protected function getLoader()
    {
        if (!$this->loader) {
            $cacheDir = $this->getCacheDir();
            $this->loader = new Loader(
                $this->getConfig(),
                $this->getServiceLocator()->get('VuFind\ContentCoversPluginManager'),
                $this->getServiceLocator()->get('VuFindTheme\ThemeInfo'),
                $this->getServiceLocator()->get('VuFind\Http')->createClient(),
                $cacheDir
            );
            \VuFind\ServiceManager\Initializer::initInstance(
                $this->loader, $this->getServiceLocator()
            );
        }
        return $this->loader;
    }

    
    public function showAction()
    {

        // Special case: proxy a full URL:
        $proxy = $this->params()->fromQuery('proxy');
        if (!empty($proxy)) {
            try {
                $image = $this->getProxy()->fetch($proxy);
                return $this->displayImage(
                    $image->getHeaders()->get('contenttype')->getFieldValue(),
                    $image->getContent()
                );
            } catch (\Exception $e) {
                // If an exception occurs, drop through to the standard case
                // to display an image unavailable graphic.
            }
        }

        // Default case -- use image loader:
        $this->getLoader()->loadImage($this->getImageParams());
        return $this->displayImage();
    }

    
    protected function displayImage($type = null, $image = null)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine(
            'Content-type', $type ?: $this->getLoader()->getContentType()
        );

        $coverImageTtl = (60 * 60 * 24 * 14); // 14 days
        $headers->addHeaderLine(
            'Cache-Control', "maxage=" . $coverImageTtl
        );
        $headers->addHeaderLine(
            'Pragma', 'public'
        );
        $headers->addHeaderLine(
            'Expires', gmdate('D, d M Y H:i:s', time() + $coverImageTtl) . ' GMT'
        );

        $response->setContent($image ?: $this->getLoader()->getImage());
        return $response;
    }
}
