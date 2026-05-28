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
        'zh-Hans' => 'zh-cn',
        'zh-CN' => 'zh-cn',
        'zh' => 'zh-cn',
        'en' => 'en-us',
        'en-US' => 'en-us',
        'ja' => 'ja',
        'ja-JP' => 'ja',
        'ko' => 'ko',
        'ko-KR' => 'ko',
    ],
    'allow_group'     => true,
];
