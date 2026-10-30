<?php
/**
 * Ecod Theme · 块声明
 *
 * 每个块 = 名称 + 图标 + 模板目录（area）+ 可放位置（slots）+ 可放页面（pages）
 * + 参数 schema + 模板。
 * 新增块只需在这里追加一条声明，并在 components/{area}/{id}.php 放模板。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @since   2026-10-29
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 */

if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* ============================================================
 * 首页
 * ========================================================== */

ecod_block_define('search', [
    'name'   => '搜索盒',
    'desc'   => '首页顶部标题、站点数据与搜索框',
    'icon'   => 'search',
    'area'   => 'home',
    'pages'  => ['home'],
    'order'  => 10,
    'params' => [
        'eyebrow'  => [
            'label'   => '眉标题',
            'type'    => 'text',
            'default' => '',
            'desc'    => '留空则使用副标题',
        ],
        'placeholder' => [
            'label'   => '搜索框提示文案',
            'type'    => 'text',
            'default' => '搜索文章、标签或关键词…',
        ],
        'hotwords' => [
            'label'   => '热搜词',
            'type'    => 'text',
            'default' => '',
            'legacy'  => 'EHotWords',
            'desc'    => '逗号分隔，留空则不显示',
        ],
        'stats'    => [
            'label'   => '显示站点数据',
            'type'    => 'switch',
            'default' => 1,
        ],
        'band'     => [
            'label'   => '数据带样式',
            'type'    => 'switch',
            'default' => 1,
            'desc'    => '开：三格玻璃数据带；关：一行文字',
        ],
        'style'    => [
            'label'   => '首屏形态',
            'type'    => 'select',
            'default' => 'immersive',
            'options' => [
                'immersive' => '沉浸式（480px，左对齐）',
                'card'      => '浅色卡片（紧凑）',
            ],
        ],
        'bg'       => [
            'label'   => '背景图',
            'type'    => 'text',
            'default' => '',
            'desc'    => '沉浸式首屏的背景图地址；留空则用主题色渐变',
        ],
        'height'   => [
            'label'   => '首屏高度',
            'type'    => 'number',
            'default' => 480,
            'min'     => 320,
            'max'     => 720,
            'desc'    => '仅背景图模式生效，单位 px',
        ],
        'align'    => [
            'label'   => '内容对齐',
            'type'    => 'select',
            'default' => 'left',
            'options' => [
                'left'   => '左对齐',
                'center' => '居中',
            ],
        ],
    ],
]);

ecod_block_define('catnav', [
    'name'   => '分类导航',
    'desc'   => '分类卡片横排，带文章数',
    'icon'   => 'folder',
    'area'   => 'home',
    'pages'  => ['home'],
    'order'  => 20,
    'params' => [
        'limit' => [
            'label'   => '显示数量',
            'type'    => 'number',
            'default' => 6,
            'min'     => 1,
            'max'     => 20,
        ],
    ],
]);

ecod_block_define('slider', [
    'name'   => '轮播',
    'desc'   => '大图轮播：可自定义图文与链接，也可取置顶 / 最新文章',
    'icon'   => 'image',
    'area'   => 'home',
    'pages'  => ['home'],
    'order'  => 30,
    'params' => [
        'slides' => [
            'label'   => '自定义幻灯片',
            'type'    => 'slides',
            'default' => '',
            'desc'    => '填了就用这里的图文（按顺序播放）；留空则按下面的来源自动取文章',
        ],
        'count'  => [
            'label'   => '轮播数量',
            'type'    => 'number',
            'default' => 3,
            'min'     => 1,
            'max'     => 8,
            'when'    => 'slides:empty',
            'desc'    => '仅在自动取文章时生效',
        ],
        'source' => [
            'label'   => '文章来源',
            'type'    => 'select',
            'default' => 'pin',
            'options' => ['pin' => '置顶文章', 'latest' => '最新文章'],
            'when'    => 'slides:empty',
            'desc'    => '没有自定义幻灯片时使用',
        ],
    ],
]);

ecod_block_define('recommend', [
    'name'   => '推荐位',
    'desc'   => '三列图文推荐',
    'icon'   => 'star',
    'area'   => 'home',
    'areas'  => ['home', 'aside'],
    'order'  => 40,
    'params' => [
        'count' => [
            'label'   => '推荐数量',
            'type'    => 'number',
            'default' => 3,
            'min'     => 1,
            'max'     => 6,
        ],
    ],
]);

ecod_block_define('hot', [
    'name'   => '热门四宫格',
    'desc'   => '按评论数或阅读量排列',
    'icon'   => 'zap',
    'area'   => 'home',
    'areas'  => ['home', 'aside'],
    'order'  => 50,
    'head'   => ['icon' => 'zap', 'md' => true],
    'params' => [
        'count' => [
            'label'   => '显示数量',
            'type'    => 'number',
            'default' => 4,
            'min'     => 2,
            'max'     => 12,
        ],
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '热门',
        ],
        'sort' => [
            'label'   => '排序依据',
            'type'    => 'select',
            'default' => 'comments',
            'options' => [
                'comments' => '评论数',
                'views'    => '阅读量',
                'rand'     => '随机',
            ],
        ],
        'style' => [
            'label'   => '版式',
            'type'    => 'select',
            'default' => 'grid',
            'options' => [
                'grid' => '四宫格（图 + 摘要）',
                'rank' => '排行列表（序号）',
            ],
        ],
    ],
]);

ecod_block_define('feed', [
    'name'   => '动态流',
    'desc'   => '时间轴式的最近更新列表',
    'icon'   => 'clock',
    'area'   => 'home',
    'areas'  => ['home', 'aside'],
    'order'  => 60,
    'head'   => ['icon' => 'clock'],
    'params' => [
        'count' => [
            'label'   => '显示数量',
            'type'    => 'number',
            'default' => 8,
            'min'     => 3,
            'max'     => 30,
        ],
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '动态',
        ],
    ],
]);

ecod_block_define('focus', [
    'name'   => '焦点双栏',
    'desc'   => '左侧大图 + 右侧标题列表',
    'icon'   => 'layers',
    'area'   => 'home',
    'pages'  => ['home'],
    'order'  => 70,
    'head'   => ['icon' => 'zap'],
    'params' => [
        'count' => [
            'label'   => '显示数量',
            'type'    => 'number',
            'default' => 6,
            'min'     => 3,
            'max'     => 12,
        ],
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '本期焦点',
        ],
    ],
]);

ecod_block_define('cat-post', [
    'name'   => '分类精选',
    'desc'   => '指定分类的文章卡片',
    'icon'   => 'hash',
    'area'   => 'home',
    'pages'  => ['home', 'cat'],
    'areas'  => ['home', 'aside'],
    'order'  => 80,
    'head'   => ['dot' => true, 'title' => '', 'title_desc' => '留空显示分类名'],
    'params' => [
        'mid'   => [
            'label'   => '分类',
            'type'    => 'term',
            'default' => 0,
            'desc'    => '留空取第一个分类',
        ],
        'count' => [
            'label'   => '显示数量',
            'type'    => 'number',
            'default' => 3,
            'min'     => 1,
            'max'     => 6,
        ],
    ],
]);

ecod_block_define('title-list', [
    'name'   => '标题速览',
    'desc'   => '最新文章列表：标题卡片或序号排行两种版式',
    'icon'   => 'file',
    'area'   => 'home',
    'areas'  => ['home', 'aside'],
    'order'  => 90,
    'head'   => ['icon' => 'file'],
    'params' => [
        'count' => [
            'label'   => '显示数量',
            'type'    => 'number',
            'default' => 10,
            'min'     => 3,
            'max'     => 30,
        ],
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '最新速览',
        ],
        'style' => [
            'label'   => '版式',
            'type'    => 'select',
            'default' => 'card',
            'options' => [
                'card' => '标题卡片',
                'rank' => '序号列表',
            ],
        ],
    ],
]);

ecod_block_define('overview', [
    'name'   => '数据概览',
    'desc'   => '文章、评论、标签、运行天数',
    'icon'   => 'chart',
    'area'   => 'home',
    'pages'  => ['home'],
    'order'  => 100,
    'params' => [
        'views' => [
            'label'   => '含阅读总量',
            'type'    => 'switch',
            'default' => 0,
        ],
    ],
]);

ecod_block_define('ad', [
    'name'   => '广告位',
    'desc'   => '单张图片横幅，未填图片则不显示',
    'icon'   => 'radio',
    'area'   => 'home',
    'order'  => 110,
    'on'     => false,
    'params' => [
        'image' => [
            'label'   => '图片地址',
            'type'    => 'image',
            'default' => '',
            'legacy'  => 'EAdImage',
            'desc'    => '从媒体库选图，或直接粘贴图片链接',
        ],
        'link'  => [
            'label'   => '跳转链接',
            'type'    => 'text',
            'default' => '',
            'legacy'  => 'EAdLink',
        ],
        'label' => [
            'label'   => '角标文字',
            'type'    => 'text',
            'default' => '广告',
        ],
    ],
]);

ecod_block_define('list', [
    'name'   => '正文流',
    'desc'   => '分页文章列表，可拖动调整位置',
    'icon'   => 'layers',
    'area'   => 'home',
    'order'  => 5,
    'required' => true,
    'params' => [
        'tabs' => [
            'label'   => '显示分类选项卡',
            'type'    => 'switch',
            'default' => 1,
        ],
        'layout' => [
            'label'   => '列表版式',
            'type'    => 'select',
            'default' => 'default',
            'options' => [
                'default' => '跟随文章设置',
                'grid'    => '网格（三列）',
                'feature' => '大图',
                'bare'    => '无图',
                'title'   => '标题列表',
                'compact' => '紧凑行（无摘要）',
                'mosaic'  => '混合网格（1 大 + N 小）',
                'item'    => '清单 / 装备卡',
            ],
        ],
    ],
]);

ecod_block_define('collection', [
    'name'   => '合集',
    'desc'   => '按分类聚合的专题卡片，封面取分类最新文章',
    'icon'   => 'layers',
    'area'   => 'home',
    'pages'  => ['home'],
    'order'  => 150,
    'on'     => false,
    'head'   => ['dot' => true],
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '合集',
        ],
        'count' => [
            'label'   => '显示数量',
            'type'    => 'number',
            'default' => 3,
            'min'     => 1,
            'max'     => 6,
        ],
        'mids'  => [
            'label'   => '指定分类',
            'type'    => 'terms',
            'default' => '',
            'desc'    => '可多选；留空则按分类顺序取前几个',
        ],
    ],
]);

ecod_block_define('topics', [
    'name'   => '专题',
    'desc'   => '专题墙切片：跨分类的策展合集，封面取专题封面或最新文章首图',
    'icon'   => 'layers',
    'area'   => 'home',
    'pages'  => ['home'],
    'order'  => 160,
    'on'     => false,
    'head'   => ['dot' => true],
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '专题',
        ],
        'count' => [
            'label'   => '显示数量',
            'type'    => 'number',
            'default' => 3,
            'min'     => 1,
            'max'     => 12,
        ],
        'cols'  => [
            'label'   => '每行列数',
            'type'    => 'select',
            'default' => '3',
            'options' => ['2' => '2 列', '3' => '3 列', '4' => '4 列'],
        ],
        'style' => [
            'label'   => '卡片形态',
            'type'    => 'select',
            'default' => 'cover',
            'options' => ['cover' => '封面卡（上图下文）', 'plain' => '文字卡（紧凑）'],
        ],
    ],
]);

ecod_block_define('partners', [
    'name'   => '合作伙伴',
    'desc'   => '品牌 Logo 墙：图片 + 名称，横排外链（JustNews 同款首页模块）',
    'icon'   => 'star',
    'area'   => 'home',
    'pages'  => ['home'],
    'order'  => 170,
    'on'     => false,
    'head'   => ['dot' => true],
    'params' => [
        'links' => [
            'label'   => '合作伙伴',
            'type'    => 'repeater',
            'default' => '',
            'max'     => 24,
            'fields'  => [
                ['key' => 'label', 'label' => '名称', 'placeholder' => '如：Typecho'],
                ['key' => 'href',  'label' => '链接', 'placeholder' => 'https://…'],
                ['key' => 'img',   'label' => 'Logo 图片', 'placeholder' => 'https://…（留空显示首字）'],
            ],
        ],
    ],
]);

ecod_block_define('flash', [
    'name'   => '快讯',
    'desc'   => '时间线形式的最新短讯，日期戳 + 标题 + 一行摘要，无封面',
    'icon'   => 'zap',
    'area'   => 'home',
    'areas'  => ['home', 'aside'],
    'order'  => 160,
    'on'     => false,
    'head'   => ['dot' => true],
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '快讯',
        ],
        'count' => [
            'label'   => '显示条数',
            'type'    => 'number',
            'default' => 6,
            'min'     => 3,
            'max'     => 12,
        ],
        'mid'   => [
            'label'   => '限定分类',
            'type'    => 'term',
            'default' => 0,
            'desc'    => '留空表示不限定（取全站最新）',
        ],
        'days'  => [
            'label'   => '时间范围',
            'type'    => 'number',
            'default' => 0,
            'min'     => 0,
            'desc'    => '只取最近 N 天的文章；填 0 表示不限',
        ],
        'desc'  => [
            'label'   => '显示摘要',
            'type'    => 'switch',
            'default' => 1,
        ],
    ],
]);

/* ============================================================
 * 侧栏
 * ========================================================== */

ecod_block_define('about', [
    'name'   => '关于本站',
    'desc'   => '站点标识、简介与数据行',
    'icon'   => 'user',
    'area'   => 'aside',
    'order'  => 10,
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '关于本站',
        ],
        'desc'  => [
            'label'   => '简介',
            'type'    => 'text',
            'default' => '',
            'desc'    => '留空则使用站点描述',
        ],
        'stats' => [
            'label'   => '显示文章/评论/分类数',
            'type'    => 'switch',
            'default' => 1,
        ],
    ],
]);

ecod_block_define('author', [
    'name'   => '作者卡片',
    'desc'   => '头像、时段问候、简介与社交链接',
    'icon'   => 'user',
    'area'   => 'aside',
    'pages'  => ['single', 'author'],
    'order'  => 15,
    'on'     => false,
    'params' => [
        'avatar' => [
            'label'   => '头像',
            'type'    => 'image',
            'default' => '',
            'desc'    => '留空则用站点名首字',
        ],
        'name'   => [
            'label'   => '名称',
            'type'    => 'text',
            'default' => '',
            'desc'    => '留空则用站点标题',
        ],
        'desc'   => [
            'label'   => '一句话简介',
            'type'    => 'textarea',
            'default' => '',
            'desc'    => '留空则用站点描述',
        ],
        'greet'  => [
            'label'   => '显示时段问候',
            'type'    => 'switch',
            'default' => 1,
        ],
        'banner' => [
            'label'   => '横幅图',
            'type'    => 'image',
            'default' => '',
            'desc'    => '卡片中部的 2:1 横幅，留空则不显示',
        ],
        'apps'   => [
            'label'   => '快捷入口磁贴',
            'type'    => 'repeater',
            'default' => '',
            'max'     => 4,
            'fields'  => [
                ['key' => 'label', 'label' => '名称', 'placeholder' => '如：创作'],
                ['key' => 'href',  'label' => '链接', 'placeholder' => 'https://…'],
                ['key' => 'icon',  'label' => '图标', 'placeholder' => '图标名：edit / star / zap…（留空用 link）'],
            ],
        ],
        'links'  => [
            'label'   => '社交链接',
            'type'    => 'repeater',
            'default' => '',
            'max'     => 12,
            'fields'  => [
                ['key' => 'label', 'label' => '名称', 'placeholder' => '如：GitHub'],
                ['key' => 'href', 'label' => '网址', 'placeholder' => 'https://…'],
            ],
        ],
        'stats'  => [
            'label'   => '显示文章/评论/天数',
            'type'    => 'switch',
            'default' => 1,
        ],
        'cta'    => [
            'label'   => '显示底部主按钮',
            'type'    => 'switch',
            'default' => 1,
            'desc'    => '登录用户跳个人中心；游客自动显示「登录 / 注册」跳登录页',
        ],
        'ctatext' => [
            'label'   => '按钮文字',
            'type'    => 'text',
            'default' => '个人中心',
        ],
        'uid'    => [
            'label'   => '博主 uid',
            'type'    => 'text',
            'default' => '',
            'desc'    => '在线点按这个用户的最近登录判定；留空自动取管理员组最早的账号',
        ],
        'online' => [
            'label'   => '显示在线点与博主徽章',
            'type'    => 'switch',
            'default' => 1,
        ],
    ],
]);

ecod_block_define('toc', [
    'name'   => '文章目录',
    'desc'   => '仅文章页显示，自动抽取正文标题',
    'icon'   => 'file',
    'area'   => 'aside',
    'pages'  => ['single'],
    'order'  => 20,
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '目录',
        ],
    ],
]);

ecod_block_define('comments', [
    'name'   => '最新评论',
    'desc'   => '带头像与摘要',
    'icon'   => 'comment',
    'area'   => 'aside',
    'order'  => 40,
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '最新评论',
        ],
        'count' => [
            'label'   => '显示数量',
            'type'    => 'number',
            'default' => 5,
            'min'     => 1,
            'max'     => 20,
        ],
    ],
]);

ecod_block_define('tags', [
    'name'   => '标签云',
    'desc'   => '标签流式排布，带文章数与字号梯度',
    'icon'   => 'tag',
    'area'   => 'aside',
    'order'  => 50,
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '标签云',
        ],
        'limit' => [
            'label'   => '最多显示',
            'type'    => 'number',
            'default' => '',
            'desc'    => '按文章数从多到少取前 N 个；留空显示全部',
        ],
    ],
]);

ecod_block_define('cats', [
    'name'   => '分类目录',
    'desc'   => '全部分类与文章数',
    'icon'   => 'folder',
    'area'   => 'aside',
    'order'  => 70,
    'on'     => false,
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '分类目录',
        ],
        'count' => [
            'label'   => '显示文章数',
            'type'    => 'switch',
            'default' => 1,
        ],
        'limit' => [
            'label'   => '最多显示',
            'type'    => 'number',
            'default' => 0,
            'min'     => 0,
            'max'     => 50,
            'desc'    => '0 表示全部',
        ],
    ],
]);

ecod_block_define('archives', [
    'name'   => '按月归档',
    'desc'   => '最近几个月与文章数',
    'icon'   => 'calendar',
    'area'   => 'aside',
    'order'  => 80,
    'on'     => false,
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '归档',
        ],
        'limit' => [
            'label'   => '显示月数',
            'type'    => 'number',
            'default' => 6,
            'min'     => 1,
            'max'     => 36,
        ],
    ],
]);

ecod_block_define('wsearch', [
    'name'   => '搜索框',
    'desc'   => '侧栏站内搜索',
    'icon'   => 'search',
    'area'   => 'aside',
    'order'  => 90,
    'on'     => false,
    'params' => [
        'title'       => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '搜索',
        ],
        'placeholder' => [
            'label'   => '提示文案',
            'type'    => 'text',
            'default' => '输入关键词回车搜索…',
        ],
    ],
]);

ecod_block_define('wstats', [
    'name'   => '站点统计',
    'desc'   => '文章、评论、分类、运行天数',
    'icon'   => 'chart',
    'area'   => 'aside',
    'order'  => 100,
    'on'     => false,
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '站点统计',
        ],
        'views' => [
            'label'   => '显示总阅读量',
            'type'    => 'switch',
            'default' => 1,
        ],
    ],
]);

ecod_block_define('rank', [
    'name'   => '排行榜',
    'desc'   => '缩略图 + 名次徽章，可按总榜/周榜/月榜切换',
    'icon'   => 'chart',
    'area'   => 'aside',
    'order'  => 105,
    'on'     => false,
    'head'   => ['title' => '热门排行'],
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '热门排行',
        ],
        'count' => [
            'label'   => '显示数量',
            'type'    => 'number',
            'default' => 5,
            'min'     => 3,
            'max'     => 10,
        ],
        'by'    => [
            'label'   => '排序依据',
            'type'    => 'select',
            'default' => 'views',
            'options' => [
                'views' => '阅读量',
                'hot'   => '评论数',
            ],
        ],
        'tabs'  => [
            'label'   => '显示周期切换',
            'type'    => 'switch',
            'default' => 1,
        ],
        'range' => [
            'label'   => '默认周期',
            'type'    => 'select',
            'default' => 'week',
            'options' => [
                'all'   => '总榜',
                'week'  => '周榜（7 天）',
                'month' => '月榜（30 天）',
            ],
        ],
    ],
]);

ecod_block_define('wtab', [
    'name'   => '热门 Tab 组',
    'desc'   => '热门 / 热评 / 最新 / 随机四合一选项卡，省侧栏纵向空间',
    'icon'   => 'zap',
    'area'   => 'aside',
    'order'  => 55,
    'on'     => false,
    'head'   => ['scope' => 'widget', 'title' => '文章看看'],
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '文章看看',
        ],
        'count' => [
            'label'   => '每版条数',
            'type'    => 'number',
            'default' => 8,
            'min'     => 3,
            'max'     => 15,
        ],
        'hot'    => [
            'label'   => '热门（按阅读量）',
            'type'    => 'switch',
            'default' => 1,
        ],
        'hotc'   => [
            'label'   => '热评（按评论数）',
            'type'    => 'switch',
            'default' => 1,
        ],
        'recent' => [
            'label'   => '最新',
            'type'    => 'switch',
            'default' => 1,
        ],
        'rand'   => [
            'label'   => '随机',
            'type'    => 'switch',
            'default' => 1,
        ],
        'days'   => [
            'label'   => '热门时间范围',
            'type'    => 'number',
            'default' => 0,
            'min'     => 0,
            'desc'    => '只统计最近 N 天；0 表示不限',
        ],
    ],
]);

/* ============================================================
 * 文章页
 * ========================================================== */

ecod_block_define('act', [
    'name'   => '互动按钮',
    'desc'   => '点赞 / 收藏 / 复制链接 / 社交分享 / 赞赏收款码',
    'icon'   => 'heart',
    'area'   => 'post',
    'pages'  => ['single'],
    'order'  => 10,
    'params' => [
        'like'  => [
            'label'   => '点赞',
            'type'    => 'switch',
            'default' => 1,
        ],
        'fav'   => [
            'label'   => '收藏',
            'type'    => 'switch',
            'default' => 1,
        ],
        'share' => [
            'label'   => '复制链接',
            'type'    => 'switch',
            'default' => 1,
        ],
        'reward' => [
            'label'   => '赞赏',
            'type'    => 'switch',
            'default' => 0,
        ],
        'alipay' => [
            'label'   => '支付宝收款码',
            'type'    => 'image',
            'default' => '',
            'when'    => 'reward:on',
            'desc'    => '留空则只显示微信',
        ],
        'wechat' => [
            'label'   => '微信收款码',
            'type'    => 'image',
            'default' => '',
            'when'    => 'reward:on',
            'desc'    => '留空则只显示支付宝',
        ],
    ],
]);

ecod_block_define('tts', [
    'name'   => '阅读工具条',
    'desc'   => '吸顶工具条：目录 / 朗读（浏览器语音合成）/ 字号 / 阅读进度，吸收原「朗读条」横幅形态；形态可在「全站设置 → 阅读工具条形态」切换为侧栏竖栏',
    'icon'   => 'list',
    'area'   => 'post',
    'pages'  => ['single'],
    'order'  => 12,
    'anchor' => 'top', // 锚定文章顶部：正文之前渲染（post.php 单独调用）
    'params' => [],
]);

ecod_block_define('copyright', [
    'name'   => '版权声明',
    'desc'   => '作者、原文链接与转载协议',
    'icon'   => 'file',
    'area'   => 'post',
    'pages'  => ['single'],
    'order'  => 15,
    'params' => [
        'title'   => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '版权声明',
        ],
        'license' => [
            'label'   => '协议',
            'type'    => 'select',
            'default' => 'by-nc-sa',
            'options' => [
                'by-nc-sa' => 'CC BY-NC-SA 4.0（署名-非商业-相同方式共享）',
                'by'       => 'CC BY 4.0（署名）',
                'reserved' => '保留所有权利',
            ],
        ],
        'notice'  => [
            'label'   => '附加说明',
            'type'    => 'textarea',
            'default' => '转载请注明出处，请勿用于商业用途。',
        ],
    ],
]);

ecod_block_define('authorbox', [
    'name'   => '文末作者卡',
    'desc'   => '正文结束后的「关于作者」：头像、简介与 TA 的文章入口',
    'icon'   => 'user',
    'area'   => 'post',
    'pages'  => ['single'],
    'order'  => 16,
    'on'     => true,
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '关于作者',
        ],
    ],
]);

ecod_block_define('postnav', [
    'name'   => '上下篇导航',
    'desc'   => '相邻文章跳转',
    'icon'   => 'arrow-up-right',
    'area'   => 'post',
    'pages'  => ['single'],
    'order'  => 20,
    'params' => [],
]);

ecod_block_define('related', [
    'name'   => '相关推荐',
    'desc'   => '同标签优先，同分类补齐，不足用最近文章兜底',
    'icon'   => 'link',
    'area'   => 'post',
    'pages'  => ['single'],
    'order'  => 30,
    'head'   => ['dot' => true],
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '相关阅读',
        ],
        'count' => [
            'label'   => '显示数量',
            'type'    => 'number',
            'default' => 3,
            'min'     => 1,
            'max'     => 8,
        ],
    ],
]);

/* ============================================================
 * 页脚
 * ========================================================== */

ecod_block_define('credits', [
    'name'   => '版权信息',
    'desc'   => '页脚左侧文案，支持 HTML',
    'icon'   => 'file',
    'area'   => 'footer',
    'order'  => 10,
    'params' => [
        'html' => [
            'label'   => '自定义内容',
            'type'    => 'textarea',
            'default' => '',
            'legacy'  => 'EFooter',
            'desc'    => '留空显示默认版权信息',
        ],
    ],
]);

ecod_block_define('siteage', [
    'name'   => '站点年龄 · 里程碑',
    'desc'   => '「本站已运行 N 天」与目标篇数进度条，数字自动统计',
    'icon'   => 'calendar',
    'area'   => 'footer',
    'order'  => 15,
    'on'     => true,
    'params' => [
        'days' => [
            'label'   => '显示已运行天数',
            'type'    => 'switch',
            'default' => 1,
        ],
        'goal' => [
            'label'   => '目标篇数',
            'type'    => 'number',
            'default' => 0,
            'min'     => 0,
            'max'     => 100000,
            'desc'    => '填 0 不显示进度条；达成后自动改成「已达成」',
        ],
    ],
]);

ecod_block_define('links', [
    'name'   => '页脚链接',
    'desc'   => '备案号、首页、RSS',
    'icon'   => 'link',
    'area'   => 'footer',
    'order'  => 20,    'params' => [
        'icp'  => [
            'label'   => '备案号',
            'type'    => 'text',
            'default' => '',
            'legacy'  => 'EICP',
        ],
        'home' => [
            'label'   => '显示首页链接',
            'type'    => 'switch',
            'default' => 1,
        ],
        'rss'  => [
            'label'   => '显示 RSS',
            'type'    => 'switch',
            'default' => 1,
        ],
    ],
]);

ecod_block_define('totop', [
    'name'   => '回到顶部',
    'desc'   => '右下角悬浮按钮',
    'icon'   => 'arrow-up',
    'area'   => 'footer',
    'order'  => 30,
    'params' => [],
]);

ecod_block_define('progress', [
    'name'   => '阅读进度',
    'desc'   => '顶部进度条',
    'icon'   => 'chart',
    'area'   => 'footer',
    'order'  => 40,
    'params' => [],
]);

ecod_block_define('theme-toggle', [
    'name'   => '深浅色切换',
    'desc'   => '右下角悬浮按钮，跟随「深色模式」设置',
    'icon'   => 'moon',
    'area'   => 'footer',
    'order'  => 50,
    'params' => [],
]);

ecod_block_define('dock', [
    'name'   => '移动端底部 dock',
    'desc'   => '手机端底部快捷栏（首页/搜索/深浅色/回顶部），启用后移动端悬浮按钮由 dock 接管',
    'icon'   => 'list',
    'area'   => 'footer',
    'order'  => 60,
    'params' => [],
]);

ecod_block_define('notice', [
    'name'   => '站点公告',
    'desc'   => '页面顶部公告：单条横幅、多条轮播或每日弹窗，多条在「公告列表」里维护',
    'icon'   => 'alert',
    'area'   => 'footer',
    'order'  => 70,
    'file'   => 'components/header/notice.php',
    'params' => [
        'mode'  => [
            'label'   => '展示方式',
            'type'    => 'select',
            'default' => 'bar',
            'options' => [
                'bar'    => '横幅（单条展示，可关闭）',
                'rotate' => '轮播（多条定时滚动）',
                'popup'  => '弹窗（内容变化后弹一次）',
            ],
        ],
        'items' => [
            'label'   => '公告列表',
            'type'    => 'repeater',
            'max'     => 10,
            'default' => '',
            'fields'  => [
                ['name' => 'text', 'label' => '公告内容（支持 HTML）'],
                ['name' => 'link', 'label' => '跳转链接（可选）'],
            ],
            'desc'    => '排在前面的先展示；与下方「公告内容」二选一，列表优先',
        ],
        'content'  => [
            'label'   => '公告内容（单条兼容）',
            'type'    => 'textarea',
            'default' => '',
            'desc'    => '支持 HTML；留空且公告列表为空则不显示公告',
        ],
        'tone'     => [
            'label'   => '样式',
            'type'    => 'select',
            'default' => 'info',
            'options' => ['info' => '信息（蓝）', 'success' => '成功（绿）', 'warn' => '提醒（橙）'],
        ],
        'link'     => [
            'label'   => '跳转链接',
            'type'    => 'text',
            'default' => '',
            'desc'    => '单条模式的「查看」入口',
        ],
        'closable' => [
            'label'   => '允许关闭',
            'type'    => 'switch',
            'default' => 1,
        ],
    ],
]);

ecod_block_define('lottery', [
    'name'   => '站点抽奖',
    'desc'   => 'Credit 抽奖挂件：消耗、中奖概率、直接兑换、库存与保底进度，可就地抽奖（参考 guaqi 组件）',
    'icon'   => 'medal',
    'area'   => 'aside',
    'order'  => 120,
    'on'     => false,
    'head'   => ['title' => '幸运抽奖', 'title_desc' => '挂件与抽奖页共用一套配置（「设置 → 变现 → 站点抽奖」）'],
    'params' => [
        'banner' => [
            'label'   => '顶部横幅图',
            'type'    => 'image',
            'default' => '',
            'desc'    => '可选；显示在标题下方，点击进入抽奖页',
        ],
        'facts' => [
            'label'   => '显示数据栏',
            'type'    => 'switch',
            'default' => 1,
            'desc'    => '消耗 / 中奖概率 / 直接兑换 / 库存 四格数据',
        ],
        'board' => [
            'label'   => '显示奖品列表',
            'type'    => 'switch',
            'default' => 1,
            'desc'    => '关闭后只留数据栏与抽奖按钮（抽奖仍按完整奖品池结算）',
        ],
    ],
]);

ecod_block_define('adbuy', [
    'name'   => '自助广告',
    'desc'   => '可购买的广告位价目卡，点击进入「广告投放」页下单（参考 guaqi 组件）',
    'icon'   => 'radio',
    'area'   => 'aside',
    'order'  => 125,
    'on'     => false,
    'head'   => ['title' => '自助开通广告', 'title_desc' => '价目来自「设置 → 变现 → 自助广告」，未定价的位子不出现'],
    'params' => [
        'limit' => [
            'label'   => '最多展示',
            'type'    => 'number',
            'min'     => 1,
            'max'     => 6,
            'default' => 3,
        ],
        'note' => [
            'label'   => '底部说明',
            'type'    => 'text',
            'default' => '',
            'desc'    => '可选，例如「按天计费，审核后上线」',
        ],
    ],
]);

ecod_block_define('ticker', [
    'name'   => '更新动态',
    'desc'   => '最新积分事件滚动播报（签到 / 中奖 / 被赞…），guaqi 式 LIVE 挂件；需开启积分并在「设置 → 互动 → 积分与 AI」打开播报开关',
    'icon'   => 'radio',
    'area'   => 'aside',
    'order'  => 128,
    'on'     => false,
    'head'   => ['title' => '更新动态', 'title_desc' => '展示站点最新的积分奖励事件'],
    'params' => [
        'limit' => [
            'label'   => '滚动条数',
            'type'    => 'number',
            'min'     => 3,
            'max'     => 15,
            'default' => 8,
        ],
        'seconds' => [
            'label'   => '一轮秒数',
            'type'    => 'number',
            'min'     => 10,
            'max'     => 120,
            'default' => 30,
        ],
    ],
]);

ecod_block_define('shop', [
    'name'   => '积分兑换',
    'desc'   => 'Credit 兑换商城挂件：商品价目卡就地兑换（「设置 → 变现 → 兑换商城」维护商品）',
    'icon'   => 'medal',
    'area'   => 'aside',
    'order'  => 122,
    'on'     => false,
    'head'   => ['title' => '积分兑换', 'title_desc' => '挂件与商城页共用一套商品配置'],
    'params' => [
        'limit' => [
            'label'   => '最多展示',
            'type'    => 'number',
            'min'     => 1,
            'max'     => 6,
            'default' => 4,
        ],
    ],
]);

ecod_block_define('uads', [
    'name'   => '投放广告',
    'desc'   => '用户在「广告投放」页自助购买并审核通过的广告（「设置 → 变现 → 自助广告」定价）',
    'icon'   => 'radio',
    'area'   => 'aside',
    'order'  => 130,
    'on'     => false,
    'params' => [
        'slot' => [
            'label'   => '拉取的广告位',
            'type'    => 'select',
            'default' => 'sidebar',
            'options' => [
                'sidebar'     => '侧边栏卡片',
                'home_top'    => '首页顶部通栏',
                'post_bottom' => '文章底部通栏',
            ],
            'desc'    => '本模块通常摆在侧栏并选「侧边栏卡片」；其余广告位请把模块放到对应区域',
        ],
        'limit' => [
            'label'   => '最多展示',
            'type'    => 'number',
            'min'     => 1,
            'max'     => 5,
            'default' => 2,
        ],
        'label' => [
            'label'   => '角标文字',
            'type'    => 'text',
            'default' => '广告',
        ],
    ],
]);
