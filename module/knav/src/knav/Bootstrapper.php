<?php
namespace knav;
use Zend\Console\Console, Zend\Mvc\MvcEvent, Zend\Mvc\Router\Http\RouteMatch;

class Bootstrapper
{
    /**
     * Main VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config = null;

    /**
     * Current MVC event
     *
     * @var MvcEvent
     */
    protected $event;

    /**
     * Event manager
     *
     * @var \Zend\EventManager\EventManagerInterface
     */
    protected $events;

    /**
     * Constructor
     *
     * @param MvcEvent $event Zend MVC Event object
     */
    public function __construct(MvcEvent $event)
    {
        $this->event = $event;
        $this->events = $event->getApplication()->getEventManager();
    }

    /**
     * Bootstrap all necessary resources.
     *
     * @return void
     */
    public function bootstrap()
    {
        // automatically call all methods starting with "init":
        $methods = get_class_methods($this);
        foreach ($methods as $method) {
            if (substr($method, 0, 4) == 'init') {
                $this->$method();
            }
        }
    }

    /**
     * Set up configuration manager.
     *
     * @return void
     */
    protected function initConfig()
    {
        // Create the configuration manager:
        $app = $this->event->getApplication();
        $serviceManager = $app->getServiceManager();
        $this->config = $serviceManager->get('VuFind\Config')->get('config');        
    }

    /**
     * Initialize dynamic debug mode (debug initiated by a ?debug=true parameter).
     *
     * @return void
     */
    protected function initDynamicDebug()
    {
        // Query parameters do not apply in console mode:
        if (Console::isConsole()) {
            return;
        }

        $app = $this->event->getApplication();
        $sm = $app->getServiceManager();
        $debugOverride = $sm->get('Request')->getQuery()->get('debug');
        if ($debugOverride) {
            $auth = $sm->get('ZfcRbac\Service\AuthorizationService');
            if ($auth->isGranted('access.DebugMode')) {
                $logger = $sm->get('VuFind\Logger');
                $logger->addDebugWriter($debugOverride);
            }
        }
    }

    /**
     * If the system is offline, set up a handler to override the routing output.
     *
     * @return void
     */
    protected function initSystemStatus()
    {
        // If the system is unavailable, forward to a different place:
        if (isset($this->config->System->available)
            && !$this->config->System->available
        ) {
            $callback = function ($e) {
                $routeMatch = new RouteMatch(
                    ['controller' => 'Error', 'action' => 'Unavailable'], 1
                );
                $routeMatch->setMatchedRouteName('error-unavailable');
                $e->setRouteMatch($routeMatch);
            };
            $this->events->attach('route', $callback);
        }
    }

    /**
     * Initializes locale and timezone values
     *
     * @return void
     */
    protected function initLocaleAndTimeZone()
    {
        // Try to set the locale to UTF-8, but fail back to the exact string from
        // the config file if this doesn't work 
        setlocale(
            LC_ALL,
            [
                "{$this->config->Site->locale}.UTF8",
                "{$this->config->Site->locale}.UTF-8",
                $this->config->Site->locale
            ]
        );
        date_default_timezone_set($this->config->Site->timezone);
    }

    /**
     * Set view variables representing the current context.
     *
     * @return void
     */
    protected function initContext()
    {
        $callback = function ($event) {
            $serviceManager = $event->getApplication()->getServiceManager();
            $viewModel = $serviceManager->get('viewmanager')->getViewModel();

            $children = $viewModel->getChildren();
            if (!empty($children)) {
                $parts = explode('/', $children[0]->getTemplate());
                $viewModel->setVariable('templateDir', $parts[0]);
                $viewModel->setVariable(
                    'templateName', isset($parts[1]) ? $parts[1] : null
                );
            }
        };
        $this->events->attach('dispatch', $callback);
    }

    /**
     * Set up headTitle view helper -- we always want to set, not append, titles.
     *
     * @return void
     */
    protected function initHeadTitle()
    {
        $callback = function ($event) {
            $serviceManager = $event->getApplication()->getServiceManager();
            $renderer = $serviceManager->get('viewmanager')->getRenderer();
            $headTitle = $renderer->plugin('headtitle');
            $headTitle->setDefaultAttachOrder(
                \Zend\View\Helper\Placeholder\Container\AbstractContainer::SET
            );
        };
        $this->events->attach('dispatch', $callback);
    }

    public function detectBrowserLanguage()
    {
        if (isset($this->config->Site->browserDetectLanguage)
            && false == $this->config->Site->browserDetectLanguage
        ) {
            return false;
        }

        // break up string into pieces (languages and q factors)
        preg_match_all(
            '/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i',
            $this->event->getRequest()->getServer()->get('HTTP_ACCEPT_LANGUAGE'),
            $langParse
        );

        if (!count($langParse[1])) {
            return false;
        }

        // create a list like "en" => 0.8
        $langs = array_combine($langParse[1], $langParse[4]);

        // set default to 1 for any without q factor
        foreach ($langs as $lang => $val) {
            if (empty($val)) {
                $langs[$lang] = 1;
            }
        }

        // sort list based on value
        arsort($langs, SORT_NUMERIC);

        $validLanguages = array_keys($this->config->Languages->toArray());

        // return first valid language
        foreach (array_keys($langs) as $language) {
            // Make sure language code is valid
            $language = strtolower($language);
            if (in_array($language, $validLanguages)) {
                return $language;
            }

            // Make sure language code is valid, reset to default if bad:
            $langStrip = current(explode("-", $language));
            if (in_array($langStrip, $validLanguages)) {
                return $langStrip;
            }
        }

        return false;
    }

    /**
     * Support method for initLanguage() -- look up all text domains.
     *
     * @return array
     */
    protected function getTextDomains()
    {
        $base = APPLICATION_PATH;
        $local = LOCAL_OVERRIDE_DIR;
        $languagePathParts = ["$base/languages"];
        if (!empty($local)) {
            $languagePathParts[] = "$local/languages";
        }
        $languagePathParts[] = "$base/themes/*/languages";

        $domains = [];
        foreach ($languagePathParts as $current) {
            $places = glob($current . '/*', GLOB_ONLYDIR | GLOB_NOSORT);
            $domains = array_merge($domains, array_map('basename', $places));
        }

        return array_unique($domains);
    }

    /**
     * Set up language handling.
     *
     * @return void
     */
    protected function initLanguage()
    {
        // Language not supported in CLI mode:
        if (Console::isConsole()) {
            return;
        }

        $config = & $this->config;
        $browserCallback = [$this, 'detectBrowserLanguage'];
        $callback = function ($event) use ($config, $browserCallback) {
            $validBrowserLanguage = call_user_func($browserCallback);

            // Setup Translator
            $request = $event->getRequest();
            $sm = $event->getApplication()->getServiceManager();
            if (($language = $request->getPost()->get('mylang', false))
                || ($language = $request->getQuery()->get('lng', false))
            ) {
                $cookieManager = $sm->get('VuFind\CookieManager');
                $cookieManager->set('language', $language);
            } elseif (!empty($request->getCookie()->language)) {
                $language = $request->getCookie()->language;
            } else {
                $language = (false !== $validBrowserLanguage)
                    ? $validBrowserLanguage : $config->Site->language;
            }

            // Make sure language code is valid, reset to default if bad:
            if (!in_array($language, array_keys($config->Languages->toArray()))) {
                $language = $config->Site->language;
            }
            try {
                $translator = $sm->get('VuFind\Translator');
                $translator->setLocale($language)
                    ->addTranslationFile('ExtendedIni', null, 'default', $language);
                foreach ($this->getTextDomains() as $domain) {
                    $translator->addTranslationFile(
                        'ExtendedIni', $domain, $domain, $language
                    );
                }
            } catch (\Zend\Mvc\Exception\BadMethodCallException $e) {
                if (!extension_loaded('intl')) {
                    throw new \Exception(
                        'Translation broken due to missing PHP intl extension.'
                        . ' Please disable translation or install the extension.'
                    );
                }
            }
            // Send key values to view:
            $viewModel = $sm->get('viewmanager')->getViewModel();
            $viewModel->setVariable('userLang', $language);
            $viewModel->setVariable('allLangs', $config->Languages);
            $rtlLangs = isset($config->LanguageSettings->rtl_langs)
                ? array_map(
                    'trim', explode(',', $config->LanguageSettings->rtl_langs)
                ) : [];
            $viewModel->setVariable('rtl', in_array($language, $rtlLangs));
        };
        $this->events->attach('dispatch.error', $callback, 10000);
        $this->events->attach('dispatch', $callback, 10000);
    }

    /**
     * Set up theme handling.
     *
     * @return void
     */
    protected function initTheme()
    {
        if (Console::isConsole()) {
            return;
        }

        // Attach template injection configuration to the route event:
        $this->events->attach(
            'route', ['VuFindTheme\Initializer', 'configureTemplateInjection']
        );

        $config = $this->config->Site;
        $callback = function ($event) use ($config) {
            $theme = new \VuFindTheme\Initializer($config, $event);
            $theme->init();
        };
        $this->events->attach('dispatch.error', $callback, 9000);
        $this->events->attach('dispatch', $callback, 9000);
    }

    /**
     * Set up custom 404 status based on exception type.
     *
     * @return void
     */
    protected function initExceptionBased404s()
    {
        // 404s not needed in console mode:
        if (Console::isConsole()) {
            return;
        }

        $callback = function ($e) {
            $exception = $e->getParam('exception');
            if (is_object($exception)) {
                if ($exception instanceof \VuFind\Exception\RecordMissing) {
                    $response = $e->getResponse();
                    if (!$response) {
                        $response = new HttpResponse();
                        $e->setResponse($response);
                    }
                    $response->setStatusCode(404);
                }
            }
        };
        $this->events->attach('dispatch.error', $callback);
    }

    /**
     * Set up search subsystem.
     *
     * @return void
     */
    protected function initSearch()
    {
        $sm     = $this->event->getApplication()->getServiceManager();
        $bm     = $sm->get('VuFind\Search\BackendManager');
        $events = $sm->get('SharedEventManager');
        $events->attach('VuFindSearch', 'resolve', [$bm, 'onResolve']);
    }

    /**
     * Set up logging.
     *
     * @return void
     */
    protected function initErrorLogging()
    {
        $callback = function ($event) {
            $sm = $event->getApplication()->getServiceManager();
            if ($sm->has('VuFind\Logger')) {
                $log = $sm->get('VuFind\Logger');
                if (is_callable([$log, 'logException'])) {
                    $exception = $event->getParam('exception');
                    $server = Console::isConsole()
                        ? new \Zend\Stdlib\Parameters(['env' => 'console'])
                        : $event->getRequest()->getServer();
                    if (!empty($exception)) {
                        $log->logException($exception, $server);
                    }
                }
            }
        };
        $this->events->attach('dispatch.error', $callback);
        $this->events->attach('render.error', $callback);
    }

    /**
     * Set up handling for rendering problems.
     *
     * @return void
     */
    protected function initRenderErrorEvent()
    {
        $callback = function ($event) {
            $serviceManager = $event->getApplication()->getServiceManager();
            $viewModel = $serviceManager->get('viewmanager')->getViewModel();
            $viewModel->renderingError = true;
        };
        $this->events->attach('render.error', $callback, 10000);
    }
}
