<?php
namespace knav\Cover;
use VuFindCode\ISBN, VuFind\Content\Covers\PluginManager as ApiManager;

/**
 * Book Cover Generator
 *
 */
class Loader extends \VuFind\Cover\Loader
{
    
    public function __construct($config, ApiManager $manager,
        \VuFindTheme\ThemeInfo $theme, \Zend\Http\Client $client, $baseDir = null
    ) {
        $this->setThemeInfo($theme);
        $this->config = $config;
        $this->configuredFailImage = isset($config->Content->noCoverAvailableImage)
            ? $config->Content->noCoverAvailableImage : null;
        $this->apiManager = $manager;
        $this->client = $client;
        $this->baseDir = (null === $baseDir)
            ? rtrim(sys_get_temp_dir(), '\\/') . '/covers'
            : rtrim($baseDir, '\\/');
    }

 
    protected function fetchFromContentType()
    {
        // Give up if no content type was passed in:
        if (empty($this->type)) {
            return false;
        }

        // Try to find an icon:
        $iconFile = $this->searchTheme(
            'images/' . $this->size . '/' . $this->type,
            ['.png', '.gif', '.jpg']
        );
        if ($iconFile !== false) {
            // Most content-type headers match file extensions... but
            // include a special case for jpg vs. jpeg:
            $format = substr($iconFile, -3);
            $this->contentType
                = 'image/' . ($format == 'jpg' ? 'jpeg' : $format);
            $this->image = file_get_contents($iconFile);
            return true;
        }

        return false;
    }

 
    protected function convertNonJpeg($imageData, $jpeg)
    {
        if (!is_callable('imagecreatefromstring')) {
            return false;
        }

        // Try to create a GD image and rewrite as JPEG, fail if we can't:
        if (!($imageGD = @imagecreatefromstring($imageData))) {
            return false;
        } else {
            $bg = imagecreatetruecolor(imagesx($imageGD), imagesy($imageGD));
            imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
            imagealphablending($bg, TRUE);
            imagecopy($bg, $imageGD, 0, 0, 0, 0, imagesx($imageGD), imagesy($imageGD));
            imagedestroy($imageGD);
        }

        if (!@imagejpeg($bg, $jpeg)) {
            return false;
        } else {
            ImageDestroy($bg);
        }

        return true;
    }

}
