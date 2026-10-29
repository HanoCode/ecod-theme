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
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
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
    'params' => [
        'mid'   => [
            'label'   => '分类 MID',
            'type'    => 'number',
            'default' => 0,
            'min'     => 0,
            'desc'    => '留 0 取第一个分类',
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
    'desc'   => '纯标题列表，信息密度高',
    'icon'   => 'file',
    'area'   => 'home',
    'areas'  => ['home', 'aside'],
    'order'  => 90,
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
            'type'    => 'text',
            'default' => '',
            'desc'    => '分类 mid，逗号分隔；留空则按分类顺序取前几个',
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

ecod_block_define('flash', [
    'name'   => '快讯',
    'desc'   => '时间线形式的最新短讯，日期戳 + 标题 + 一行摘要，无封面',
    'icon'   => 'zap',
    'area'   => 'home',
    'areas'  => ['home', 'aside'],
    'order'  => 160,
    'on'     => false,
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
            'type'    => 'number',
            'default' => 0,
            'min'     => 0,
            'desc'    => '分类 mid；填 0 表示不限定（取全站最新）',
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
        'links'  => [
            'label'   => '社交链接',
            'type'    => 'textarea',
            'default' => '',
            'desc'    => '每行一条，格式：名称|网址',
        ],
        'stats'  => [
            'label'   => '显示文章/评论数',
            'type'    => 'switch',
            'default' => 1,
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

ecod_block_define('recent', [
    'name'   => '最新文章',
    'desc'   => '序号列表',
    'icon'   => 'clock',
    'area'   => 'aside',
    'order'  => 30,
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '最新文章',
        ],
        'count' => [
            'label'   => '显示数量',
            'type'    => 'number',
            'default' => 6,
            'min'     => 1,
            'max'     => 20,
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

ecod_block_define('popular', [
    'name'   => '热门文章',
    'desc'   => '按评论热度 / 阅读量 / 随机',
    'icon'   => 'zap',
    'area'   => 'aside',
    'order'  => 60,
    'on'     => false,
    'params' => [
        'title' => [
            'label'   => '标题',
            'type'    => 'text',
            'default' => '热门文章',
        ],
        'by'    => [
            'label'   => '排序依据',
            'type'    => 'select',
            'default' => 'hot',
            'options' => [
                'hot'   => '评论热度',
                'views' => '阅读量',
                'rand'  => '随机',
            ],
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

ecod_block_define('aside-ad', [
    'name'   => '侧栏广告位',
    'desc'   => '单张图片横幅，可带跳转链接',
    'icon'   => 'image',
    'area'   => 'aside',
    'order'  => 110,
    'on'     => false,
    'file'   => 'components/aside/ad.php',
    'params' => [
        'image' => [
            'label'   => '图片',
            'type'    => 'image',
            'default' => '',
            'desc'    => '从媒体库选图，或直接粘贴图片链接',
        ],
        'link'  => [
            'label'   => '跳转链接',
            'type'    => 'text',
            'default' => '',
        ],
        'label' => [
            'label'   => '角标文字',
            'type'    => 'text',
            'default' => '广告',
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
    'name'   => '朗读条',
    'desc'   => '用浏览器语音合成朗读正文，可调语速、显示进度',
    'icon'   => 'volume',
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
    'desc'   => '页面顶部横幅，可关闭（关闭后按内容记忆），支持跳转链接',
    'icon'   => 'alert',
    'area'   => 'footer',
    'order'  => 70,
    'file'   => 'components/header/notice.php',
    'params' => [
        'content'  => [
            'label'   => '公告内容',
            'type'    => 'textarea',
            'default' => '',
            'hint'    => '支持 HTML；留空则不显示公告',
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
            'hint'    => '可选，填了内容右侧会显示「查看」箭头',
        ],
        'closable' => [
            'label'   => '允许关闭',
            'type'    => 'switch',
            'default' => 1,
        ],
    ],
]);
