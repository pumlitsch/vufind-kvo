<?php

$config = [
  'service_manager' => [
    'factories' => [
      'VuFind\\CacheManager' => 'knav\\Service\\Factory::getCacheManager',
    ],
  ],
  'vufind' => [
    'plugin_managers' => [
      'recorddriver' => [
        'factories' => [
          'solrdefault' => 'knav\\RecordDriver\\Factory::getSolrDefault',
          'solrmarc' => 'knav\\RecordDriver\\Factory::getSolrMarc',
        ],
      ],
    ],
    'recorddriver_tabs' => [
      'knav\\RecordDriver\\SolrDefault' => [
          'tabs' => [
              'Description' => 'Description',
              'UserComments' => 'UserComments',
              'Details' => 'StaffViewArray',
          ],
          'defaultTab' => null,
          // 'backgroundLoadedTabs' => ['UserComments', 'Details']
      ],
      'knav\\RecordDriver\\SolrMarc' => [
          'tabs' => [
              'Description' => 'Description',
              'UserComments' => 'UserComments',
              'Details' => 'StaffViewMARC',
          ],
          'defaultTab' => null,
      ],
    ],
  ],
  'controllers' => [
    'invokables' => [
      'ajax' => 'knav\\Controller\\AjaxController',
      'query' => 'knav\\Controller\\SolrRedirectController',
    ],
  ],
];

// Add the home route last

$config['router']['routes']['home'] = [
    'type' => 'Zend\\Mvc\\Router\\Http\\Segment',
        'options' => [
            'route'    => '/',
            'defaults' => [
                'controller' => 'index',
                'action'     => 'Home',
        ],
    ],
];



return $config;

