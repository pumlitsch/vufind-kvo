<?php
return array(
    'extends' => 'root',
    'css' => array(
        //'vendor/bootstrap.min.css',
        //'vendor/bootstrap-accessibility.css',
        //'bootstrap-custom.css',
        'compiled.css',
        'vendor/font-awesome.min.css',
        'vendor/bootstrap-slider.min.css',
        'print.css:print',
        'magnific-popup.css',
        'vufind.css',
    ),
    'js' => array(
        'vendor/base64.js:lt IE 10', // btoa polyfill
        'vendor/jquery.min.js',
        'vendor/bootstrap.min.js',
        'vendor/bootstrap-accessibility.min.js',
        //'vendor/bootlint.min.js',
        'autocomplete.js',
        'vendor/validator.min.js',
        'vendor/rc4.js',
        'common.js',
        'lightbox.js',
        'selectbox.js',
        'obalkyknih/custom.js',
        'obalkyknih/functions.js',
        'check_historical_collection.js',
        'jquery.magnific-popup.min.js',
        'knav.js'
    ),
    'less' => array(
        'active' => false,
        'compiled.less'
    ),
    'favicon' => 'vufind-favicon.ico',
    'helpers' => array(
        'factories' => array(
            'citation' => 'knav\View\Helper\Root\Factory::getCitation',
            'flashmessages' => 'VuFind\View\Helper\Bootstrap3\Factory::getFlashmessages',
            'layoutclass' => 'VuFind\View\Helper\Bootstrap3\Factory::getLayoutClass',
            'recaptcha' => 'VuFind\View\Helper\Bootstrap3\Factory::getRecaptcha',
        ),
        'invokables' => array(
            'highlight' => 'VuFind\View\Helper\Bootstrap3\Highlight',
            'renderarray' => 'knav\View\Helper\Root\RenderArray',
            'record' => 'knav\View\Helper\Root\Record',
            'search' => 'VuFind\View\Helper\Bootstrap3\Search',
            'vudl' => 'VuDL\View\Helper\Bootstrap3\VuDL',
        )
    )
);
