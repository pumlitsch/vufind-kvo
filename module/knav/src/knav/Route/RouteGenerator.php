<?php

namespace knav\Route;

class RouteGenerator
{

    protected $nonTabRecordActions;

 
    public function __construct(array $nonTabRecordActions = null)
    {
        if (null === $nonTabRecordActions) {
            $this->nonTabRecordActions = [
                'AddComment', 'DeleteComment', 'AddTag', 'DeleteTag', 'Save',
                'Email', 'SMS', 'Cite', 'Export', 'RDF', 'Hold', 'BlockedHold',
                'Home', 'StorageRetrievalRequest', 'AjaxTab',
                'BlockedStorageRetrievalRequest', 'ILLRequest', 'BlockedILLRequest',
                'PDF',
            ];
        } else {
            $this->nonTabRecordActions = $nonTabRecordActions;
        }
    }

    public function addDynamicRoute(& $config, $routeName, $controller, $action)
    {
        list($actionName) = explode('/', $action, 2);
        $config['router']['routes'][$routeName] = [
            'type'    => 'Zend\Mvc\Router\Http\Segment',
            'options' => [
                'route'    => "/$controller/$action",
                'constraints' => [
                    'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                ],
                'defaults' => [
                    'controller' => $controller,
                    'action'     => $actionName,
                ]
            ]
        ];
    }

    public function addDynamicRoutes(& $config, $routes)
    {
        // Build library card routes
        foreach ($routes as $controller => $controllerRoutes) {
            foreach ($controllerRoutes as $routeName => $action) {
                $this->addDynamicRoute($config, $routeName, $controller, $action);
            }
        }
    }

    public function addRecordRoute(& $config, $routeBase, $controller, $inst = NULL)
    {
        $config['router']['routes'][$routeBase] = [
            'type'    => 'Zend\Mvc\Router\Http\Segment',
            'options' => [
                'route'    => empty($inst)? '/' . $controller . '/[:id[/[:tab]]]' : '/' . $inst . '/' . $controller . '/[:id[/[:tab]]]',
                'constraints' => [
                    'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                    'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                ],
                'defaults' => [
                    'controller' => $controller,
                    'action'     => 'Home',
                ]
            ]
        ];
        // special non-tab actions that each need their own route:
        foreach ($this->nonTabRecordActions as $action) {
            $config['router']['routes'][$routeBase . '-' . strtolower($action)] = [
                'type'    => 'Zend\Mvc\Router\Http\Segment',
                'options' => [
                    'route'    => empty($inst)? '/' . $controller . '/[:id]/' . $action :  '/' . $inst . '/' . $controller . '/[:id]/' . $action,
                    'constraints' => [
                        'controller' => '[a-zA-Z][a-zA-Z0-9_-]*',
                        'action'     => '[a-zA-Z][a-zA-Z0-9_-]*',
                    ],
                    'defaults' => [
                        'controller' => $controller,
                        'action'     => $action,
                    ]
                ]
            ];
        }
    }

    public function addRecordRoutes(& $config, $routes, $inst = NULL)
    {
        foreach ($routes as $routeBase => $controller) {
            $this->addRecordRoute($config, $routeBase, $controller, $inst);
        }
    }

      public function addStaticRoute(& $config, $route, $inst = NULL)
    {
        list($controller, $action) = explode('/', $route);
        $routeName = str_replace('/', '-', strtolower($route));
        $config['router']['routes'][$routeName] = [
            'type' => 'Zend\Mvc\Router\Http\Literal',
            'options' => [
                'route'    => empty($inst) ? '/' . $route : '/' . $inst . '/' . $route,
                'defaults' => [
                    'controller' => $controller,
                    'action'     => $action,
                ]
            ]
        ];
    }

    public function addStaticRoutes(& $config, $routes, $inst = FALSE)
    {
        foreach ($routes as $route) {
            $this->addStaticRoute($config, $route, $inst);
        }
    }
}
