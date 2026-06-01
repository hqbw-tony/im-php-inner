<?php

return [
    'default_lang'    => env('lang.default_lang', 'zh-cn'),
    'allow_lang_list' => [],
    'detect_var'      => 'lang',
    'use_cookie'      => true,
    'cookie_var'      => 'think_lang',
    'extend_list'     => [
        'zh-cn' => [
            app()->getBasePath() . 'lang/zh_cn.php',
        ],
        'en-us' => [
            app()->getBasePath() . 'lang/en_us.php',
        ],
        'ja' => [
            app()->getBasePath() . 'lang/ja.php',
        ],
        'ko' => [
            app()->getBasePath() . 'lang/ko.php',
        ],
    ],
    'accept_language' => [
        'zh-hans' => 'zh-cn',
        'zh-cn' => 'zh-cn',
        'zh' => 'zh-cn',
        'en' => 'en-us',
        'en-us' => 'en-us',
        'ja' => 'ja',
        'ja-jp' => 'ja',
        'ko' => 'ko',
        'ko-kr' => 'ko',
    ],
    'allow_group'     => true,
];
