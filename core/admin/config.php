<?php
/**
 * Ecod Theme · 后台设置页
 *
 * 由 functions.php 的 themeConfig($form) 载入，$form 在当前作用域可用。
 * 结构：左侧分组导航 + 右侧面板；字段用 data-ecod-pane 标记归组，
 * 由 assets/admin/config.js 搬运到对应面板，保存逻辑仍走 Typecho 原生表单。
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

/* ------------------------------------------------------------------
 * 分组
 * ---------------------------------------------------------------- */
$ecodPanes = [
    'site'       => ['站点', '标识、图标、配色与备案'],
    'nav'        => ['导航', '一级与二级菜单：拖拽排序、缩进成二级'],
    'topics'     => ['专题', '跨分类的策展合集：列表、封面与挑稿'],
    'appearance' => ['外观', '深色模式、导航、背景与动效'],
    'home'       => ['首页', 'Banner、标语与模块布局'],
    'layout'     => ['版式', '侧栏位置、卡片样式与摘要'],
    'aside'      => ['侧栏', '侧栏模块与顺序'],
    'post'       => ['文章页', '正文模块与阅读体验'],
    'footer'     => ['页脚', '版权、链接与浮动控件'],
    'interact'   => ['互动', '点赞、邮件通知与审核规则'],
    'pay'        => ['付费', '付费阅读与会员：定价、支付通道与展示'],
    'comments'   => ['评论', '嵌套折叠、第三方评论、人机验证与反垃圾'],
    'ai'         => ['AI 助手', '智能摘要：OpenAI 兼容接口，按文章生成摘要'],
    'seo'        => ['收录与性能', '搜索收录、懒加载与自定义代码'],
    'search'     => ['搜索', '快捷搜索面板：热词、标签与实时联想'],
    'tools'      => ['工具', '配置备份、导入与恢复'],
];


/* 由块系统接管的分组（模块已迁到独立管理台，这里只剩常规字段与入口卡） */
$ecodBlockPanes = ['home', 'aside', 'post', 'footer'];

/* 模块管理台入口（独立页面，不在后台菜单里）
   注意：basename(__DIR__ . '/..') 会得到 ".."，必须用 dirname() 取主题目录名 */
$ecodWidgetUrl = rtrim((string) \Widget\Options::alloc()->siteUrl, '/')
    . '/usr/themes/' . basename(dirname(dirname(__DIR__))) . '/widget.php';

/* 分组级条件：整组因外部开关失效时，顶部出现提示条
   结构：[条件表达式, 提示文案, 跳转目标「分组:字段」] */
$ecodPaneConds = [
    'comments' => ['EComment:on', '全站评论已关闭，本组设置暂不生效', 'interact:EComment'],
    'aside'    => ['EAside:!=none', '侧栏位置为「无侧栏」，本组模块不会输出', 'layout:EAside'],
];

/* 默认激活的分组：取第一个，避免引用不存在的键导致首屏空白 */
$ecodFirstPane = array_key_first($ecodPanes);

/**
 * 注册一个后台字段并打好分组标记
 */
if (!function_exists('ecod_admin_field')) {
    function ecod_admin_field($form, string $type, string $name, $value, string $label = '', string $desc = '', array $options = [], string $pane = 'basic', array $attrs = [])
    {
        $map = [
            'text'     => 'Text',
            'textarea' => 'Textarea',
            'select'   => 'Select',
            'number'   => 'Number',
            'hidden'   => 'Hidden',
        ];
        $class = '\\Typecho\\Widget\\Helper\\Form\\Element\\' . ($map[$type] ?? 'Text');

        if ($type === 'select') {
            $element = new $class($name, $options, $value, $label, $desc);
        } elseif ($type === 'hidden') {
            $element = new $class($name, null, (string) $value);
        } else {
            $element = new $class($name, null, $value, $label, $desc);
        }

        /* min / max / step 等属性：数字项带范围时前端会渲染成滑块 */
        foreach ($attrs as $attrName => $attrValue) {
            $element->input->setAttribute($attrName, (string) $attrValue);
        }

        $element->setAttribute('data-ecod-pane', $pane);
        $form->addInput($element);
        return $element;
    }
}

/**
 * 带媒体库选图能力的字段
 *
 * data-ecod-media 必须挂在 input / textarea 上：挂在 <ul> 上会让前端
 * 把整个字段容器当成输入控件，出现预览错位。
 */
if (!function_exists('ecod_admin_media')) {
    function ecod_admin_media($form, string $type, string $name, $value, string $label = '', string $desc = '', string $pane = 'basic', string $mode = 'single', array $attrs = [])
    {
        $element = ecod_admin_field($form, $type, $name, $value, $label, $desc, [], $pane, $attrs);
        $element->input->setAttribute('data-ecod-media', $mode);
        return $element;
    }
}
?>

<link rel="stylesheet" href="<?php echo ecod_asset('assets/admin/config.css'); ?>">
<link rel="stylesheet" href="<?php echo ecod_asset('assets/admin/media.css'); ?>">
<link rel="stylesheet" href="<?php echo ecod_asset('assets/admin/nav.css'); ?>">
<link rel="stylesheet" href="<?php echo ecod_asset('assets/admin/topics.css'); ?>">

<?php /* JS 被禁用或加载失败时，自绘面板无法搬运字段，隐藏外壳让 Typecho 原生表单直接可用 */ ?>
<noscript>
    <style>
        .ecod-admin, .ecod-drawer { display: none !important; }
        .typecho-option { display: block !important; }
    </style>
</noscript>

<div class="ecod-admin" id="ecod-admin">
    <aside class="ecod-admin__aside">
        <div class="ecod-admin__brand">
            <span class="ecod-admin__mark">E</span>
            <span class="ecod-admin__brandtext">
                <b>Ecod</b>
                <small>主题设置</small>
            </span>
        </div>

        <div class="ecod-admin__search">
            <div class="ecod-search__box">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
                <input type="search" data-ecod-search placeholder="搜索设置项" aria-label="搜索设置项" autocomplete="off">
                <kbd data-search-hint>/</kbd>
            </div>
            <div class="ecod-searchres" data-search-results hidden></div>
        </div>

        <nav class="ecod-admin__nav" aria-label="设置分组">
            <?php foreach ($ecodPanes as $key => $pane): ?>
                <button type="button" class="ecod-admin__navitem<?php echo $key === $ecodFirstPane ? ' is-active' : ''; ?>"
                        data-pane="<?php echo $key; ?>">
                    <?php echo htmlspecialchars($pane[0]); ?>
                </button>
            <?php endforeach; ?>
        </nav>

        <div class="ecod-admin__sidefoot">
            <button type="button" class="ecod-admin__ghost" data-ecod-export>导出配置</button>
        </div>
    </aside>

    <div class="ecod-admin__main">
        <header class="ecod-admin__head">
            <div>
                <h2 class="ecod-admin__title" data-pane-title><?php echo htmlspecialchars($ecodPanes[$ecodFirstPane][0]); ?></h2>
                <p class="ecod-admin__desc" data-pane-desc><?php echo htmlspecialchars($ecodPanes[$ecodFirstPane][1]); ?></p>
            </div>
            <div class="ecod-admin__actions">
                <span class="ecod-admin__dirty" data-dirty hidden>有未保存的修改</span>
            </div>
        </header>

        <div class="ecod-admin__body" data-admin-body>
            <?php foreach ($ecodPanes as $key => $pane): ?>
                <?php if ($key === 'tools') {
                    continue;
                } ?>
                <?php $ecodPaneCond = $ecodPaneConds[$key] ?? null; ?>
                <section class="ecod-pane<?php echo $key === $ecodFirstPane ? ' is-active' : ''; ?>" data-pane-panel="<?php echo $key; ?>"<?php if ($ecodPaneCond): ?> data-ecod-pane-when="<?php echo htmlspecialchars($ecodPaneCond[0], ENT_QUOTES, 'UTF-8'); ?>" data-ecod-pane-note="<?php echo htmlspecialchars($ecodPaneCond[1], ENT_QUOTES, 'UTF-8'); ?>" data-ecod-pane-jump="<?php echo htmlspecialchars($ecodPaneCond[2], ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                    <?php if (in_array($key, $ecodBlockPanes, true)): ?>
                        <?php /* 块管理已迁到独立模块管理台，这里只留常规字段与入口卡 */ ?>
                        <a class="ecod-wgt-entry" href="<?php echo htmlspecialchars($ecodWidgetUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                            <span class="ecod-wgt-entry__ic"><?php echo ecod_icon('layers', 16); ?></span>
                            <span class="ecod-wgt-entry__text">
                                <b>本分组的模块在「模块管理台」里摆放</b>
                                <small>拖拽排序、开关、改参数，改完即存，不再占用设置页</small>
                            </span>
                            <span class="ecod-wgt-entry__go">打开 →</span>
                        </a>
                        <div class="ecod-fields" data-fields="<?php echo $key; ?>"></div>
                    <?php elseif ($key === 'nav'): ?>
                        <div class="ecod-navb__bar" data-navb-bar>
                            <label class="ecod-navb__pick">
                                <span>当前导航</span>
                                <select data-navb-select></select>
                            </label>
                            <button type="button" class="ecod-admin__ghost" data-navb-new>＋ 新建</button>
                            <button type="button" class="ecod-admin__ghost" data-navb-dup>复制</button>
                            <button type="button" class="ecod-admin__ghost" data-navb-setedit>编辑</button>
                            <button type="button" class="ecod-admin__ghost is-danger" data-navb-del>删除</button>
                            <span class="ecod-navb__bar-hint">选中哪套，前台就用哪套；改完点右下角「保存设置」</span>
                        </div>
                        <div class="ecod-navb__setform" data-navb-setform hidden>
                            <label class="ecod-navb__setfield">
                                <span>名称</span>
                                <input type="text" data-set-name maxlength="24" placeholder="例如 主导航">
                            </label>
                            <label class="ecod-navb__setfield ecod-navb__setfield--wide">
                                <span>备注</span>
                                <input type="text" data-set-note maxlength="60" placeholder="只存后台，例如「春节活动期用」，可留空">
                            </label>
                            <button type="button" class="ecod-admin__ghost" data-set-cancel>取消</button>
                            <button type="button" class="ecod-btn ecod-btn--sm" data-set-save>应用</button>
                        </div>
                        <div class="ecod-navb" data-navb>
                            <div class="ecod-navb__col">
                                <div class="ecod-navb__hd">添加到菜单</div>
                                <div class="ecod-navb__tabs" data-navb-tabs>
                                    <button type="button" class="is-on" data-navb-tab="page">独立页面</button>
                                    <button type="button" data-navb-tab="cat">分类</button>
                                    <button type="button" data-navb-tab="tag">标签</button>
                                    <button type="button" data-navb-tab="link">自定义</button>
                                </div>
                                <div class="ecod-navb__pane" data-navb-pane="page">
                                    <input type="search" class="ecod-navb__filter" data-navb-filter placeholder="筛选页面…" autocomplete="off">
                                    <div class="ecod-navb__src" data-navb-src="page"></div>
                                </div>
                                <div class="ecod-navb__pane" data-navb-pane="cat" hidden>
                                    <input type="search" class="ecod-navb__filter" data-navb-filter placeholder="筛选分类…" autocomplete="off">
                                    <div class="ecod-navb__src" data-navb-src="cat"></div>
                                </div>
                                <div class="ecod-navb__pane" data-navb-pane="tag" hidden>
                                    <input type="search" class="ecod-navb__filter" data-navb-filter placeholder="筛选标签…" autocomplete="off">
                                    <div class="ecod-navb__src" data-navb-src="tag"></div>
                                </div>
                                <div class="ecod-navb__pane" data-navb-pane="link" hidden>
                                    <div class="ecod-navb__form">
                                        <input type="text" data-navb-link-label placeholder="名称，例如 GitHub">
                                        <input type="text" data-navb-link-url placeholder="https://github.com/you" spellcheck="false">
                                        <label class="ecod-navb__check"><input type="checkbox" data-navb-link-blank> 新窗口打开</label>
                                        <button type="button" class="ecod-btn ecod-btn--sm" data-navb-link-add>添加链接</button>
                                    </div>
                                </div>
                            </div>

                            <div class="ecod-navb__col ecod-navb__col--main">
                                <div class="ecod-navb__hd">
                                    <span data-navb-set>主导航</span>
                                    <em data-navb-count>0 项</em>
                                    <span class="ecod-navb__hint">拖动排序 · 向右多拖 28px 缩进为二级</span>
                                    <button type="button" class="ecod-admin__ghost" data-navb-clear>清空</button>
                                </div>
                                <div class="ecod-navb__list" data-navb-list>
                                    <i class="ecod-navb__marker" data-navb-marker hidden aria-hidden="true"></i>
                                </div>
                                <p class="ecod-navb__empty" data-navb-empty>这套导航还没有菜单项。左侧挑一个加进来；整套为空时前台沿用「全部独立页面 + 自定义链接」的自动导航。</p>
                            </div>
                        </div>
                        <div class="ecod-fields" data-fields="<?php echo $key; ?>"></div>
                    <?php elseif ($key === 'topics'): ?>
                        <div class="ecod-tpb__bar" data-tpb-bar>
                            <button type="button" class="ecod-btn ecod-btn--sm" data-tpb-new>＋ 新建专题</button>
                            <button type="button" class="ecod-admin__ghost" data-tpb-page>创建专题页</button>
                            <span class="ecod-tpb__hint" data-tpb-pagehint>专题页：/topics</span>
                            <span class="ecod-tpb__count" data-tpb-count>0 / 30</span>
                        </div>
                        <p class="ecod-tpb__tip">
                            专题是「编辑挑出来的一组文章」：一篇文章通过自定义字段「专题」归入一个专题，
                            也可以直接在这里挑稿加入。改完点右下角「保存设置」。
                        </p>
                        <div class="ecod-tpb" data-tpb>
                            <div class="ecod-tpb__list" data-tpb-list></div>
                            <p class="ecod-tpb__empty" data-tpb-empty>还没有专题。点「＋ 新建专题」开始，建好后到文章里选它即可归档。</p>
                        </div>
                        <div class="ecod-fields" data-fields="<?php echo $key; ?>"></div>
                    <?php else: ?>
                        <div class="ecod-fields" data-fields="<?php echo $key; ?>"></div>
                    <?php endif; ?>
                </section>
            <?php endforeach; ?>

            <section class="ecod-pane" data-pane-panel="tools">
                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>配置快照</b>
                        <p>把当前全部设置与模块布局存一份快照，最多保留 10 份。改坏了可以一键回到之前的状态；恢复前会自动把「现在」也存一份。</p>
                    </div>
                    <button type="button" class="ecod-btn" data-ecod-snapshot-save>保存快照</button>
                </div>

                <div class="ecod-snaplist" data-ecod-snaplist>
                    <?php $ecodSnaps = ecod_snapshot_list(); ?>
                    <?php if (!$ecodSnaps): ?>
                        <p class="ecod-snaplist__empty">还没有快照。点右上角「保存快照」创建第一份。</p>
                    <?php else: ?>
                        <?php foreach ($ecodSnaps as $ecodSnap): ?>
                            <div class="ecod-snap" data-name="<?php echo htmlspecialchars($ecodSnap['name'], ENT_QUOTES, 'UTF-8'); ?>">
                                <div class="ecod-snap__main">
                                    <b><?php echo htmlspecialchars($ecodSnap['label'], ENT_QUOTES, 'UTF-8'); ?></b>
                                    <small><?php echo (int) $ecodSnap['count']; ?> 项设置 · <?php echo round($ecodSnap['size'] / 1024, 1); ?> KB</small>
                                </div>
                                <div class="ecod-snap__act">
                                    <button type="button" class="ecod-admin__ghost" data-ecod-snap-restore>恢复</button>
                                    <button type="button" class="ecod-admin__ghost is-danger" data-ecod-snap-delete>删除</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>导出配置</b>
                        <p>把当前全部设置保存成 JSON 文件，换主题或重装后可原样恢复。模块布局的备份请用「模块管理台」右上角的导出。</p>
                    </div>
                    <button type="button" class="ecod-btn" data-ecod-export>下载 JSON</button>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>导入配置</b>
                        <p>选择之前导出的 JSON 文件，设置项会被回填，确认无误后再点「保存设置」。</p>
                    </div>
                    <label class="ecod-btn ecod-btn--ghost">
                        选择文件
                        <input type="file" accept="application/json,.json" data-ecod-import hidden>
                    </label>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>sitemap.xml</b>
                        <p>把站点全部文章、页面、分类、标签写成一个静态文件放到网站根目录。未开启伪静态时，PHP 路由无法接管 <code>/sitemap.xml</code>，用这里生成的文件最稳。</p>
                    </div>
                    <button type="button" class="ecod-btn ecod-btn--ghost" data-ecod-build="sitemap">生成文件</button>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>robots.txt</b>
                        <p>生成一份带 sitemap 地址的基础 robots.txt。根目录已有同名文件时不会覆盖，避免冲掉你自己的规则。</p>
                    </div>
                    <button type="button" class="ecod-btn ecod-btn--ghost" data-ecod-build="robots">生成文件</button>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>百度主动推送</b>
                        <p>把最近 20 篇文章地址推给百度，加快收录。Token 在「收录与性能」分组里填，每天配额有限，不要反复点。</p>
                    </div>
                    <button type="button" class="ecod-btn ecod-btn--ghost" data-ecod-build="baidu">推送</button>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>特色页</b>
                        <p>一键创建三个独立页面并自动选好模板，不用再手动建页、选「自定义模板」。已存在的不会重复创建。</p>
                    </div>
                </div>

                <div class="ecod-pagelist" data-ecod-pagelist>
                    <?php $ecodNavHidden = array_filter(array_map('trim', explode(',', (string) ecod_option('ENavHide', '')))); ?>
                    <?php foreach (ecod_pages_state() as $ecodPageKey => $ecodPage): ?>
                        <div class="ecod-page-row<?php echo $ecodPage['exists'] ? ' is-exists' : ''; ?>" data-key="<?php echo htmlspecialchars($ecodPageKey, ENT_QUOTES, 'UTF-8'); ?>">
                            <span class="ecod-page-row__icon" aria-hidden="true"><?php echo ecod_icon((string) $ecodPage['icon'], 17); ?></span>
                            <div class="ecod-page-row__main">
                                <b><?php echo htmlspecialchars($ecodPage['pagetitle'], ENT_QUOTES, 'UTF-8'); ?></b>
                                <small><?php echo htmlspecialchars($ecodPage['desc'], ENT_QUOTES, 'UTF-8'); ?></small>
                            </div>
                            <?php if ($ecodPage['exists']): ?>
                                <?php $ecodPageInNav = !in_array((string) $ecodPage['slug'], $ecodNavHidden, true); ?>
                                <label class="ecod-navsw" title="在导航栏显示这个页面">
                                    <span>导航</span>
                                    <span class="ecod-switch">
                                        <input type="checkbox" data-nav-toggle="<?php echo htmlspecialchars((string) $ecodPage['slug'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo $ecodPageInNav ? ' checked' : ''; ?>>
                                        <i></i>
                                    </span>
                                </label>
                            <?php endif; ?>
                            <div class="ecod-page-row__act">
                                <?php if ($ecodPage['exists']): ?>
                                    <a class="ecod-admin__ghost" href="<?php echo htmlspecialchars($ecodPage['url'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">查看</a>
                                    <a class="ecod-admin__ghost" href="<?php echo htmlspecialchars($ecodPage['edit'], ENT_QUOTES, 'UTF-8'); ?>">编辑</a>
                                <?php else: ?>
                                    <button type="button" class="ecod-btn ecod-btn--sm" data-ecod-page-create>创建</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="ecod-tool">
                    <div class="ecod-tool__text">
                        <b>模块管理台</b>
                        <p>模块的位置、开关与参数已迁到独立管理台（数据存于 <code>EWidgets</code>，旧的 <code>EBlocks</code> 只作升级迁移来源）。侧栏的按页面显示也在管理台里调。</p>
                    </div>
                    <a class="ecod-btn ecod-btn--sm" href="<?php echo htmlspecialchars($ecodWidgetUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">打开管理台</a>
                </div>

                <p class="ecod-tool__status" data-ecod-tool-status hidden></p>
            </section>
        </div>
    </div>
</div>

<?php
/* ------------------------------------------------------------------
 * 字段注册
 * ---------------------------------------------------------------- */

/* 站点 */
ecod_admin_field($form, 'text', 'ELogo', 'Ecod', '站点标识', '导航栏左侧显示的文字，留空则使用站点标题', [], 'site');
ecod_admin_media($form, 'text', 'ELogoImage', '', 'Logo 图片', '从媒体库选图或粘贴地址；填了就代替上面的文字', 'site');
ecod_admin_field($form, 'select', 'ELogoBoth', 'off', '图片旁同时显示文字', '只在填了 Logo 图片时生效，适合「图形 + 站名」并排的排布', [
    'off' => '只显示图片',
    'on'  => '图片 + 文字',
], 'site', ['data-ecod-show-when' => 'ELogoImage:!empty']);
ecod_admin_field($form, 'text', 'ESlogan', '', '副标题 / 一句话', '首页大图与搜索盒下方显示，留空则不显示', [], 'home');
ecod_admin_media($form, 'text', 'EBanner', '', '首页大图', '从媒体库选图，或直接粘贴图片 URL；留空则关闭首页大图', 'home');
ecod_admin_media($form, 'text', 'EFavicon', '', '站点图标（Favicon）', '建议 32×32 以上的 PNG / SVG；留空则用主色生成的占位图标', 'site');
$ecodPrimaryField = ecod_admin_field($form, 'text', 'EPrimary', '#4f6ef2', '主题主色', '六位十六进制色值，按钮、链接、高亮等全部跟随；留空恢复 #4f6ef2', [], 'site');
$ecodPrimaryField->input->setAttribute('data-ecod-color', '1');
ecod_admin_field($form, 'text', 'EICP', '', 'ICP 备案号', '例如「京ICP备12345678号-1」；填写后页脚自动显示并链接到工信部', [], 'site');
ecod_admin_field($form, 'text', 'EPolice', '', '公安备案号', '例如「京公网安备 11010502030123号」；填写后页脚显示并链接到公安备案查询', [], 'site');

/* 外观：登录页 */
ecod_admin_media($form, 'text', 'ELoginBg', '', '登录页背景图', '从媒体库选图或粘贴图片 URL；设置后登录页整页铺满该图并加深色遮罩，留空则用默认浅色背景', 'appearance');

/* 版式（侧栏位置、卡片、缩略图、摘要） */
ecod_admin_field($form, 'select', 'EAside', 'right', '侧栏位置（全站默认）', '所有页面类型的默认值；在「模块管理台」里可以按页面类型单独覆盖，文章 / 页面还能用自定义字段 aside 单独覆盖', [
    'right' => '右侧栏',
    'left'  => '左侧栏',
    'none'  => '无侧栏',
], 'layout');
ecod_admin_field($form, 'select', 'ECardMode', 'default', '默认卡片模式', '文章可在编辑页通过自定义字段 mode 单独覆盖', [
    'default' => '列表（文左图右）',
    'feature' => '大图（重点文章）',
    'gallery' => '三图',
    'bare'    => '无图',
    'grid'    => '网格',
    'title'   => '标题式',
    'compact' => '紧凑行（无摘要）',
    'mosaic'  => '混合网格（1 大 + N 小）',
    'item'    => '清单 / 装备（价格·规格·评分）',
], 'layout');
ecod_admin_field($form, 'select', 'EThumbSide', 'right', '缩略图位置', '列表与大图模式下缩略图放左还是放右；文章可用自定义字段 side = left / right 单独覆盖', [
    'right' => '右图（默认）',
    'left'  => '左图',
], 'layout', ['data-ecod-show-when' => 'ECardMode:default|feature']);
ecod_admin_field($form, 'number', 'ENewDays', '7', '「新」角标天数', '文章发布后多少天内显示「新」角标，填 0 关闭该角标', [], 'layout', ['min' => 0, 'max' => 90, 'step' => 1]);
ecod_admin_field($form, 'number', 'EExcerpt', '110', '摘要长度', '单位：字符，默认 110；文章里填了「自定义摘要」时以手填内容为准', [], 'layout', ['min' => 40, 'max' => 200, 'step' => 5, 'data-ecod-show-when' => 'ECardMode:!=bare|title|compact']);
ecod_admin_field($form, 'text', 'EStale', '180', '文章过期提醒', '文章超过这么多天未更新时，正文上方提示内容可能已过时；填 0 关闭', [], 'post');
ecod_admin_field($form, 'select', 'EFloatToc', 'on', '浮动目录', '文章页目录以浮动弹窗呈现，位置与侧栏互斥：侧栏在右则目录浮在左，侧栏在左则浮在右，不再遮挡侧栏；关闭后回退为侧栏里的静态目录卡', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'post');
ecod_admin_field($form, 'select', 'ETocProgress', 'on', '目录进度条', '浮动目录按钮套阅读进度环，弹层头部显示进度条与「x / n 节」，当前节之前的项目标已读；关闭后回到纯按钮形态。页面顶部进度条不受此开关影响', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'post');
ecod_admin_field($form, 'select', 'ELikers', 'on', '点赞头像堆叠', '互动条点赞按钮下方展示最近点赞的注册用户头像（最多 5 个，超出并入「等 N 人」）；还没有注册用户点赞时自动不输出，只保留数字', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'post');
ecod_admin_field($form, 'select', 'EQuickBar', 'on', '快速评论条', '移动端滚过一屏正文且评论区还在下方时，视口底部淡入胶囊条；点击平滑滚到评论区并聚焦输入框。桌面不显示', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'post');
ecod_admin_field($form, 'select', 'EDark', 'auto', '深色模式', '关闭后不加载切换按钮与首帧脚本', [
    'auto' => '跟随系统',
    'on'   => '允许手动切换',
    'off'  => '关闭',
], 'appearance');
ecod_admin_media($form, 'textarea', 'EThumbnail', '', '默认缩略图池', '文章没有封面时从这里随机取一张。每行一个地址，也可以直接从媒体库多选', 'layout', 'multi', ['data-ecod-show-when' => 'ECardMode:!=bare|title|compact']);
ecod_admin_field($form, 'select', 'EThumbServer', 'on', '服务端缩略图裁剪', '列表封面按位置自动裁剪成小尺寸 WebP（需 PHP GD 扩展，仅对本站上传的图片生效）；远程图与生成失败时回退原图', [
    'on'  => '开启',
    'off' => '关闭',
], 'layout', ['data-ecod-show-when' => 'ECardMode:!=bare|title|compact']);
ecod_admin_field($form, 'select', 'ELike', 'on', '列表卡片点赞', '首页 / 归档卡片右上角的点赞按钮；文章页互动条在「文章页 → 互动按钮」里单独控制', [
    'on'  => '显示',
    'off' => '隐藏',
], 'interact');
ecod_admin_field($form, 'select', 'EAuthorOnline', 'on', '作者卡在线点', '侧栏作者卡头像右下显示在线呼吸点（博主 5 分钟内登录过即判定在线）与「博主」徽章；博主 uid 在模块管理台的作者卡片参数里配置，留空自动识别', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'interact');
ecod_admin_field($form, 'select', 'EFriendApply', 'on', '友链一键申请', '友链页显示「一键申请」按钮与弹窗，访客填完即入库待审；审核通过后自动写入友链页正文。关闭后只保留页面里的说明文字', [
    'on'  => '开启',
    'off' => '关闭',
], 'interact');
ecod_admin_field($form, 'select', 'ECtxMenu', 'on', '右键菜单', '在文章卡片、文章页与链接上右键时给出「复制链接 / 新标签打开 / 搜索选中文字」等快捷操作；表单与输入框内仍是浏览器原生菜单', [
    'on'  => '开启',
    'off' => '关闭',
], 'appearance');

/* 阅读体验（文章页）与收录 */
ecod_admin_field($form, 'select', 'ECode', 'off', '代码高亮', '开启后由本地 Prism 统一给代码块染色，外壳底色同步跟随下方「高亮配色」；关闭则改用内置高亮器（恒深色块），不加载任何额外资源', [
    'off' => '关闭',
    'on'  => '开启',
], 'post');
ecod_admin_field($form, 'select', 'ECodeTheme', 'prism', '高亮配色', 'auto 跟随明暗模式自动切换（浅色 prism / 深色 tomorrow）；也可固定指定一套。代码块底栏与代码区共用同一底色，不会出现深浅拼接', [
    'auto'           => '跟随明暗模式',
    'prism'          => 'prism 浅色',
    'coy'            => 'coy 浅色',
    'solarizedlight' => 'solarized light',
    'tomorrow'       => 'tomorrow 深色',
    'okaidia'        => 'okaidia 深色',
    'dark'           => 'dark 深色',
], 'post', ['data-ecod-show-when' => 'ECode:on']);
ecod_admin_field($form, 'select', 'ELightbox', 'on', '正文图片灯箱', '点开正文图片全屏预览，支持左右切换与 Esc 关闭', [
    'on'  => '开启',
    'off' => '关闭',
], 'post');
ecod_admin_field($form, 'select', 'EOembed', 'on', '正文 oEmbed 自动嵌入', '正文里独占一行的 YouTube / 哔哩哔哩 / X / 网易云等地址自动渲染成播放器或卡片；代码块里的地址不处理，优先于图片懒加载与外链中转', [
    'on'  => '开启',
    'off' => '关闭',
], 'post');
ecod_admin_field($form, 'select', 'EOembedSocial', 'on', '社交卡片第三方脚本', 'X / Instagram / TikTok 需要加载对应站点的脚本才能显示内容；关闭后只保留原始链接，不请求任何第三方脚本', [
    'on'  => '加载',
    'off' => '不加载',
], 'post', ['data-ecod-show-when' => 'EOembed:on']);
ecod_admin_field($form, 'select', 'ELazy', 'on', '图片懒加载', '正文图片进入视口附近才真正请求（data-src 方案），长文首屏更快', [
    'on'  => '开启',
    'off' => '关闭',
], 'seo');
ecod_admin_field($form, 'select', 'EViews', 'on', '阅读量统计', '关闭后不再累加，已记录的数值仍照常显示', [
    'on'  => '统计',
    'off' => '停止统计',
], 'interact');
ecod_admin_field($form, 'text', 'EBirth', '', '站点成立日期', '例如 2021-01-01；填写后「数据概览」块会显示已运行天数', [], 'site');

/* 互动（评论 / 点赞 / 阅读量） */
ecod_admin_field($form, 'select', 'EComment', 'on', '全站评论', '关闭后所有文章与页面的评论表单、评论列表都不再输出', [
    'on'  => '开启',
    'off' => '关闭',
], 'interact');
ecod_admin_field($form, 'textarea', 'ESensitive', '', '敏感词', '命中任意一个就把评论转为「待审核」。一行一个，也支持用 || 分隔', [], 'interact', ['data-ecod-show-when' => 'EComment:on']);
ecod_admin_field($form, 'select', 'ECnOnly', 'off', '必须包含中文', '不含中文的评论转待审，用来挡纯英文机器人广告', [
    'off' => '不限制',
    'on'  => '必须含中文',
], 'interact', ['data-ecod-show-when' => 'EComment:on']);
ecod_admin_field($form, 'number', 'EMaxLen', '', '评论字数上限', '超出上限的评论转待审；填 0 或留空不限制', [], 'interact', ['min' => 0, 'max' => 3000, 'step' => 50, 'data-ecod-show-when' => 'EComment:on']);
ecod_admin_field($form, 'select', 'ECommentMail', 'off', '邮件通知总开关', '控制全站发信：评论提醒、回复通知、账户验证码、友链通知都走这里的 SMTP；关闭后以上邮件都不发，发送失败只写日志，不影响评论与申请提交', [
    'off' => '关闭',
    'on'  => '开启',
], 'interact');
$ecodMailWhen = ['data-ecod-show-when' => 'ECommentMail:on'];
ecod_admin_field($form, 'text', 'ECommentMailHost', '', 'SMTP 服务器', '例如 smtp.qq.com', [], 'interact', $ecodMailWhen);
ecod_admin_field($form, 'text', 'ECommentMailPort', '465', 'SMTP 端口', 'SSL 一般 465，STARTTLS 一般 587', [], 'interact', $ecodMailWhen);
ecod_admin_field($form, 'select', 'ECommentMailSecure', 'ssl', '加密方式', '按邮箱服务商的要求选择', [
    'ssl'  => 'SSL',
    'tls'  => 'STARTTLS',
    'none' => '不加密',
], 'interact', $ecodMailWhen);
ecod_admin_field($form, 'select', 'ECommentMailVerify', 'on', '校验 SMTP 证书', '开启后验证邮件服务器的 TLS 证书（防中间人窃取授权码）；个别邮箱中继证书链不全、连不上时再关', [
    'on'  => '校验（推荐）',
    'off' => '不校验',
], 'interact', $ecodMailWhen);
ecod_admin_field($form, 'text', 'ECommentMailAccount', '', '发件邮箱', '例如 yourname@qq.com', [], 'interact', $ecodMailWhen);
ecod_admin_field($form, 'text', 'ECommentMailPassword', '', '邮箱授权码', '注意是授权码，不是邮箱登录密码', [], 'interact', $ecodMailWhen);
ecod_admin_field($form, 'text', 'ECommentMailFrom', '', '发件人昵称', '留空则使用站点标题', [], 'interact', $ecodMailWhen);
ecod_admin_field($form, 'text', 'ECommentMailTo', '', '接收提醒的邮箱', '留空则发给上面填的发件邮箱', [], 'interact', $ecodMailWhen);
ecod_admin_field($form, 'select', 'EReplyMail', 'off', '回复通知评论者', '评论收到回复时，用同一套 SMTP 给被回复者发邮件；自己回复自己不发送', [
    'off' => '关闭',
    'on'  => '开启',
], 'interact', $ecodMailWhen);
ecod_admin_field($form, 'select', 'EAccountMail', 'off', '账户邮件（注册 / 找回密码）', '复用上面的 SMTP 账号给用户发验证码邮件；开启后登录页才会出现「找回密码」入口', [
    'off' => '关闭',
    'on'  => '开启',
], 'interact', $ecodMailWhen);
/* 友链通知模板：变量 {站点名称} {站点地址} {一句话简介} {联系邮箱} {头像地址} {本站名称} {本站地址} */
$ecodFriendMailWhen = ['data-ecod-show-when' => 'ECommentMail:on,EFriendApply:on'];
ecod_admin_field($form, 'text', 'EFriendMailSubject', '【友链申请】{站点名称}', '友链申请通知 · 主题', '有人提交友链申请时发给博主自己；需先开启上方「邮件通知总开关」并填好 SMTP。可用变量：{站点名称} {站点地址} {本站名称}', [], 'interact', $ecodFriendMailWhen);
ecod_admin_field($form, 'textarea', 'EFriendMailBody', "收到一条新的友链申请。\n\n站点名称：{站点名称}\n站点地址：{站点地址}\n一句话简介：{一句话简介}\n联系邮箱：{联系邮箱}\n\n到友链页的「待审核申请」面板可以一键通过。", '友链申请通知 · 正文', '纯文本即可，空行分段、链接自动可点。可用变量：{站点名称} {站点地址} {一句话简介} {联系邮箱} {头像地址} {本站名称} {本站地址}', [], 'interact', ['rows' => 8, 'data-ecod-show-when' => 'ECommentMail:on,EFriendApply:on']);
ecod_admin_field($form, 'text', 'EFriendPassSubject', '友链申请已通过 · {本站名称}', '审核通过通知 · 主题', '审核通过时发给申请人填的邮箱。可用变量同上', [], 'interact', $ecodFriendMailWhen);
ecod_admin_field($form, 'textarea', 'EFriendPassBody', "你好，「{站点名称}」的友链申请已通过审核。\n\n站点地址：{站点地址}\n\n已经加入友链列表，感谢分享，欢迎常来。\n{本站地址}", '审核通过通知 · 正文', '发给申请人；留空或某个模板为空时使用内置默认文案', [], 'interact', ['rows' => 6, 'data-ecod-show-when' => 'ECommentMail:on,EFriendApply:on']);
ecod_admin_field($form, 'select', 'EOAuthGithub', 'off', 'GitHub 登录', '登录页显示「使用 GitHub 登录」；需先在 GitHub 注册 OAuth App（回调地址填：站点地址 + /usr/themes/ecod-theme/api.php?action=oauth_callback&platform=github）', [
    'off' => '关闭',
    'on'  => '开启',
], 'interact');
$ecodOAuthWhen = ['data-ecod-show-when' => 'EOAuthGithub:on'];
ecod_admin_field($form, 'text', 'EOAuthGithubId', '', 'GitHub Client ID', 'GitHub OAuth App 的 Client ID', [], 'interact', $ecodOAuthWhen);
ecod_admin_field($form, 'text', 'EOAuthGithubSecret', '', 'GitHub Client Secret', 'GitHub OAuth App 的 Client Secret，注意保密', [], 'interact', $ecodOAuthWhen);
ecod_admin_field($form, 'select', 'EOAuthBindMail', 'on', '按邮箱自动绑定', '第三方账号的邮箱与本站用户邮箱一致时，首次登录自动绑定（关闭后需账号密码登录一次才能绑定）', [
    'off' => '关闭',
    'on'  => '开启',
], 'interact', $ecodOAuthWhen);
ecod_admin_field($form, 'select', 'EOAuthRegister', 'on', '第三方自动建号', '第三方账号没有匹配到本站用户时自动创建 subscriber 账号', [
    'off' => '关闭',
    'on'  => '开启',
], 'interact', $ecodOAuthWhen);

/* ============================================================
 * 评论：嵌套展示 + 外部服务
 *
 * 第三方评论系统接管时，原生评论列表与表单都不再输出；
 * Turnstile / Akismet 只作用于原生评论（第三方由服务端自己处理）。
 * ========================================================== */
ecod_admin_field($form, 'select', 'ECommentProvider', 'native', '评论服务', '选第三方评论系统后，原生评论列表与表单不再输出，由外部服务完整接管（文章页、独立页面通用）', [
    'native' => '原生（Typecho）',
    'twikoo' => 'Twikoo',
    'waline' => 'Waline',
    'artalk' => 'Artalk',
    'giscus' => 'Giscus（GitHub Discussions）',
], 'comments', ['data-ecod-show-when' => 'EComment:on']);
ecod_admin_field($form, 'text', 'ECommentTwikoo', '', 'Twikoo 环境地址', '填 envId（腾讯云开发环境 ID）或自建服务地址 https://twikoo.example.com', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:twikoo']);
ecod_admin_field($form, 'text', 'ECommentWaline', '', 'Waline 服务地址', '例如 https://waline.example.com，末尾不要带 /', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:waline']);
ecod_admin_field($form, 'text', 'ECommentArtalk', '', 'Artalk 服务地址', '例如 https://artalk.example.com', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:artalk']);
ecod_admin_field($form, 'text', 'ECommentGiscusRepo', '', 'Giscus 仓库', 'owner/repo，仓库需公开并已开启 Discussions', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:giscus']);
ecod_admin_field($form, 'text', 'ECommentGiscusRepoId', '', 'Giscus Repo ID', '在 giscus.app 配置页复制 data-repo-id', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:giscus']);
ecod_admin_field($form, 'text', 'ECommentGiscusCategory', '', 'Giscus 分类名', '例如 Announcements', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:giscus']);
ecod_admin_field($form, 'text', 'ECommentGiscusCategoryId', '', 'Giscus 分类 ID', '在 giscus.app 配置页复制 data-category-id', [], 'comments', ['data-ecod-show-when' => 'ECommentProvider:giscus']);
ecod_admin_field($form, 'select', 'ECommentGiscusMapping', 'pathname', 'Giscus 映射方式', '决定哪篇文章对应哪个 Discussion；改这个值等于换一套讨论串', [
    'pathname' => '按路径（推荐）',
    'title'    => '按标题',
    'url'      => '按完整地址',
    'og:title' => '按 og:title',
], 'comments', ['data-ecod-show-when' => 'ECommentProvider:giscus']);
ecod_admin_field($form, 'select', 'ECommentFold', '3', '嵌套折叠深度', '前 N 层始终展开，更深的回复收进「展开 N 条回复」，点开后再显示。Typecho 后台「讨论」里的最大嵌套层数决定上限', [
    '2' => '2 层',
    '3' => '3 层（推荐）',
    '4' => '4 层',
    '5' => '5 层',
], 'comments', ['data-ecod-show-when' => 'ECommentProvider:native,ECommentExpandMode:!all']);
ecod_admin_field($form, 'select', 'ECommentExpandMode', 'manual', '长讨论的展开方式', '决定深层回复怎么呈现：「逐条展开」每条折叠各自有按钮；「顶部总按钮」在评论列表上方给一个「展开全部回复」，一次铺开所有折叠；「全部展开」不再折叠，长讨论会把页面拉得很长', [
    'manual' => '逐条展开（默认）',
    'button' => '顶部总按钮（展开全部回复）',
    'all'    => '全部展开（不折叠）',
], 'comments', ['data-ecod-show-when' => 'ECommentProvider:native']);
ecod_admin_field($form, 'select', 'ETurnstile', 'off', '人机验证 Turnstile', 'Cloudflare 免费人机验证，提交评论时服务端校验；只作用于原生评论', [
    'off' => '关闭',
    'on'  => '开启',
], 'comments', ['data-ecod-show-when' => 'ECommentProvider:native']);
ecod_admin_field($form, 'text', 'ETurnstileSite', '', 'Turnstile Site Key', '在 Cloudflare 控制台 → Turnstile 创建站点后获得（公开值）', [], 'comments', ['data-ecod-show-when' => 'ETurnstile:on']);
ecod_admin_field($form, 'text', 'ETurnstileSecret', '', 'Turnstile Secret Key', '同一页面的私密密钥，只在服务端使用，不会输出到前台', [], 'comments', ['data-ecod-show-when' => 'ETurnstile:on']);
ecod_admin_field($form, 'select', 'EAkismet', 'off', '反垃圾 Akismet', '提交时把评论送 Akismet 检测，判定为垃圾的直接标记为 spam（前台与后台默认列表都不显示）', [
    'off' => '关闭',
    'on'  => '开启',
], 'comments', ['data-ecod-show-when' => 'ECommentProvider:native']);
ecod_admin_field($form, 'text', 'EAkismetKey', '', 'Akismet API Key', '在 akismet.com 获取；填错或网络不通时放行，不影响正常评论', [], 'comments', ['data-ecod-show-when' => 'EAkismet:on']);
ecod_admin_field($form, 'select', 'EEmojiStyle', 'unicode', '评论表情', 'Emoji 是字符，全平台显示最稳；图片表情包用的是主题内置的 31 个表情，可在下面换成外部 CDN', [
    'unicode' => '系统 Emoji（推荐）',
    'smiley'  => '图片表情包',
], 'comments', ['data-ecod-show-when' => 'ECommentProvider:native']);
ecod_admin_field($form, 'text', 'EEmojiCdn', '', '表情包 CDN 前缀', '填了就走外部 CDN，例如 https://cdn.example.com/smiley/；留空用主题本地 assets/img/smiley/', [], 'comments', ['data-ecod-show-when' => 'EEmojiStyle:smiley']);

/* ============================================================
 * AI 助手（文章页异步生成摘要，结果按正文 hash 缓存；见 core/content/ai.php）
 * ========================================================== */
ecod_admin_field($form, 'select', 'EAI', 'off', 'AI 智能摘要', '总开关。开启后文章页顶部会显示 AI 生成的摘要卡片，摘要按正文内容缓存，正文更新后自动重新生成', [
    'on'  => '开启',
    'off' => '关闭（默认）',
], 'ai');
$ecodAiWhen = ['data-ecod-show-when' => 'EAI:on'];
ecod_admin_field($form, 'select', 'EAIProvider', 'openai', '接口协议', '均走 OpenAI 兼容的 chat/completions 协议，选「自定义」时按下面的 API 地址原样请求', [
    'openai'   => 'OpenAI 兼容',
    'deepseek' => 'DeepSeek',
    'qwen'     => '通义千问',
    'zhipu'    => '智谱',
    'custom'   => '自定义',
], 'ai', $ecodAiWhen);
ecod_admin_field($form, 'text', 'EAIEndpoint', '', 'API 地址', '例如 https://api.deepseek.com/v1/chat/completions', [], 'ai', $ecodAiWhen);
ecod_admin_field($form, 'text', 'EAIKey', '', 'API Key', '在服务方控制台获取；仅保存在站点配置里，不会输出到前台页面', [], 'ai', $ecodAiWhen);
ecod_admin_field($form, 'text', 'EAIModel', '', '模型名称', '例如 deepseek-chat、qwen-plus、glm-4-flash', [], 'ai', $ecodAiWhen);
ecod_admin_field($form, 'textarea', 'EAIPrompt', '', '摘要提示词', '留空使用主题内置提示词；接入后可在这里控制摘要的语气与长度', [], 'ai', $ecodAiWhen);

/* 注入与性能 */
ecod_admin_field($form, 'textarea', 'ECustomCss', '', '自定义 CSS', '不用写 style 标签；圆角、间距、字号都能在这里覆盖', [], 'appearance');
ecod_admin_field($form, 'textarea', 'ECustomHead', '', 'head 追加代码', '原样输出到 </head> 之前，用于站长验证、第三方样式表等', [], 'seo');
ecod_admin_field($form, 'textarea', 'ECustomBody', '', 'body 末尾代码', '原样输出到 </body> 之前，用于统计脚本、客服悬浮窗等', [], 'seo');
ecod_admin_field($form, 'text', 'EFont', '', '自定义字体地址', 'woff / woff2 直链，会以 @font-face 注入并作为全站首选字体', [], 'appearance');
ecod_admin_field($form, 'text', 'ECdn', '', '静态资源 CDN', '把主题 assets 目录传到 CDN 后填前缀，例如 https://cdn.example.com/ecod-theme', [], 'seo');
ecod_admin_field($form, 'text', 'EBaidu', '', '百度推送 Token', '填了之后可在「工具」分组里一键把最新文章推给百度，加快收录', [], 'seo');

/* 外观与行为 */
ecod_admin_field($form, 'select', 'EPjax', 'on', '无刷新跳转（PJAX）', '站内点击链接只替换页面主体，不重新加载整页', [
    'on'  => '开启（推荐）',
    'off' => '关闭',
], 'appearance');
ecod_admin_field($form, 'select', 'ELinkGo', 'on', '外链中转页', '文章 / 页面里的站外链接先经过 /go/ 加载页（显示目标地址并倒计时 5 秒自动跳转），站内链接不受影响', [
    'on'  => '开启（推荐）',
    'off' => '关闭',
], 'appearance');
/* 导航：菜单为空时才使用的旧版自定义链接（保留用于快速补一两条） */
ecod_admin_field($form, 'textarea', 'ENavLinks', '', '兜底自定义链接', '仅在「菜单结构」为空时生效：一行一个，格式「名称 || 地址 || 新窗口」，第三段可省略。例如：GitHub || https://github.com/you || 1。菜单配好后这里会被忽略', [], 'nav', ['rows' => 4]);
ecod_admin_field($form, 'number', 'ENavMax', '', '导航最多显示', '超过这个数量的条目收进「更多」下拉；填 0 或留空则全部平铺。一级与二级都按这个规则处理', [], 'nav', ['min' => 0, 'max' => 12, 'step' => 1]);
ecod_admin_field($form, 'select', 'EAnimate', 'none', '列表入场动效', '滚动到视口才播放，不影响首屏', [
    'none' => '关闭',
    'fade' => '淡入',
    'up'   => '上浮淡入',
], 'appearance');
ecod_admin_field($form, 'select', 'ECursor', 'off', '鼠标跟随光点', '轻量光晕，触屏设备自动不加载', [
    'off' => '关闭',
    'dot' => '光点',
], 'appearance');
ecod_admin_field($form, 'select', 'EBackdrop', 'none', '站点背景', '纯 CSS 渐变，不增加任何请求', [
    'none' => '纯色',
    'soft' => '双色柔光',
    'mesh' => '网格渐变',
], 'appearance');

/* ============================================================
 * 付费与会员（门槛 / 权益 / 交易三层，见 core/pay/pay.php）
 * ========================================================== */
ecod_admin_field($form, 'select', 'EPay', 'off', '付费阅读总开关', '关闭时整条链路零查询、零输出；开启后按文章自定义字段里的「付费模式」生效', [
    'off' => '关闭（默认）',
    'on'  => '开启',
], 'pay');
$ecodPayWhen = ['data-ecod-show-when' => 'EPay:on'];
ecod_admin_field($form, 'text', 'EPayCurrency', '¥', '货币符号', '显示在价格前，例如 ¥ / $ / 积分', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'select', 'EPayAuthorFree', 'on', '作者本人免费', '作者浏览自己的付费文章时不锁定；管理员与编辑始终可读', [
    'on'  => '是（推荐）',
    'off' => '否',
], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'select', 'EPayBox', 'top', '付费卡位置', '解锁提示卡放在正文前、正文后，或两处都放（文末那张为精简版）', [
    'top'    => '正文前',
    'bottom' => '正文后',
    'both'   => '两处都放',
    'off'    => '不显示（只隐藏正文）',
], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'number', 'EPayTeaser', '80', '试读字数', '没有 <!--paymore--> 标记时，正文只保留前面这么多可见字；填 0 则完全不试读', [], 'pay', ['min' => 0, 'max' => 2000, 'step' => 10, 'data-ecod-show-when' => 'EPay:on,EPayBox:!=off']);
ecod_admin_field($form, 'number', 'EPayDays', '0', '默认购买有效期（天）', '0 = 永久。作者可在文章里单独填写天数覆盖', [], 'pay', ['min' => 0, 'max' => 3650, 'step' => 1, 'data-ecod-show-when' => 'EPay:on']);

/* 管理台入口：主题无法注册后台菜单，这里给出可直接打开的地址 */
$ecodPayAdminUrl = '';
try {
    $ecodPayAdminUrl = rtrim((string) \Utils\Helper::options()->themeUrl, '/') . '/pay-admin.php';
} catch (\Throwable $e) {
    $ecodPayAdminUrl = '';
}
ecod_admin_field($form, 'text', 'EPayAdminUrl', $ecodPayAdminUrl, '付费管理台', '确认收款、授予权益、生成卡密都在这个页面（仅站长可打开，建议收藏）。地址已填在右侧，复制打开即可', [], 'pay', ['readonly' => 'readonly']);

/* 支付通道 */
ecod_admin_field($form, 'select', 'EPayChannel', 'manual', '默认支付通道', '没有支付账号时用「手动确认」或「卡密」；易支付需要填完整下面三项才会出现在收银台', [
    'manual' => '手动确认（线下 / 转账）',
    'card'   => '卡密兑换',
    'epay'   => '易支付（支付宝 / 微信）',
], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'textarea', 'EPayManualDoc', '', '手动付款说明', '选「手动确认」时显示在收银台，例如收款码说明、联系方式、备注要求。留空使用内置文案', [], 'pay', ['rows' => 4, 'data-ecod-show-when' => 'EPayChannel:manual']);
$ecodEpayWhen = ['data-ecod-show-when' => 'EPayChannel:epay'];
ecod_admin_field($form, 'text', 'EPayEpayUrl', '', '易支付接口地址', '例如 https://pay.example.com（不带 /submit.php）', [], 'pay', $ecodEpayWhen);
ecod_admin_field($form, 'text', 'EPayEpayPid', '', '易支付商户 ID', '易支付后台的 PID', [], 'pay', $ecodEpayWhen);
ecod_admin_field($form, 'text', 'EPayEpayKey', '', '易支付商户密钥', '只保存在站点配置里，不会输出到前台', [], 'pay', $ecodEpayWhen);
ecod_admin_field($form, 'select', 'EPayEpayType', 'alipay', '易支付默认方式', '回调地址会自动带上 channel=epay，无需在易支付后台另填', [
    'alipay' => '支付宝',
    'wxpay'  => '微信支付',
], 'pay', $ecodEpayWhen);

/* 会员档位：名称 + 四个周期价 */
ecod_admin_field($form, 'text', 'EVip1Name', '会员', '会员名称', '显示在付费卡与购买页', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip1Month', '', '会员 · 月价（元）', '留空表示不提供该周期', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip1Quarter', '', '会员 · 季价（元）', '留空表示不提供该周期', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip1Year', '', '会员 · 年价（元）', '留空表示不提供该周期', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip1Forever', '', '会员 · 永久价（元）', '留空表示不提供', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip2Name', '超级会员', '超级会员名称', '显示在付费卡与购买页', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip2Month', '', '超级会员 · 月价（元）', '留空表示不提供该周期', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip2Quarter', '', '超级会员 · 季价（元）', '留空表示不提供该周期', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip2Year', '', '超级会员 · 年价（元）', '留空表示不提供该周期', [], 'pay', $ecodPayWhen);
ecod_admin_field($form, 'text', 'EVip2Forever', '', '超级会员 · 永久价（元）', '留空表示不提供', [], 'pay', $ecodPayWhen);

/* 隐藏：模块状态 */
/* （旧 EBlocks 隐藏字段已移除：模块布局存于独立行 EWidgets，由模块管理台读写） */
/* 隐藏：多套导航 JSON（由「导航」分组的构建器写入，core/site/nav.php 读取） */
ecod_admin_field($form, 'hidden', 'ENavSets', '', '', '', [], 'nav');

/* ---- 专题：构建器写入的 JSON + 展示项 ---- */
/* 隐藏：专题 JSON（由「专题」分组的构建器写入，core/site/topics.php 读取） */
ecod_admin_field($form, 'hidden', 'ETopics', '', '', '', [], 'topics');
ecod_admin_field($form, 'select', 'ETopicWallStyle', 'cover', '专题墙卡片形态', '封面卡信息量大；文字卡更紧凑，适合专题较多的站', [
    'cover' => '封面卡（上图下文）',
    'plain' => '文字卡（紧凑）',
], 'topics');
ecod_admin_field($form, 'select', 'ETopicWallCols', '3', '专题墙列数', '窄屏（≤720px）自动降为单列', [
    '2' => '2 列',
    '3' => '3 列',
    '4' => '4 列',
], 'topics');
ecod_admin_field($form, 'select', 'ETopicPerPage', '12', '专题内每页篇数', '专题详情页一次列多少篇；篇数多时翻页更少，但首屏更重', [
    '6'  => '6 篇',
    '12' => '12 篇',
    '18' => '18 篇',
    '24' => '24 篇',
], 'topics');
ecod_admin_field($form, 'select', 'ETopicMoreCount', '8', '详情页「其它专题」', '专题内容读完后的横向 chips，把读者留在专题层；选 0 整行隐藏（面包屑仍有专题入口）', [
    '0'  => '不显示',
    '4'  => '4 个',
    '8'  => '8 个',
    '12' => '12 个',
], 'topics');
ecod_admin_field($form, 'select', 'ETopicCardChip', 'on', '文章卡片显示专题', '在分类、日期旁加一枚专题标签；已做批量预取，不影响列表性能', [
    'on'  => '显示',
    'off' => '不显示',
], 'topics');

/* 分类页头（E1） */
$ecodCatHeadWhen = ['data-ecod-show-when' => 'ECatHead:on'];
ecod_admin_field($form, 'select', 'ECatHead', 'on', '分类页页头', '分类页顶部显示名称、描述、统计与相关专题；关闭后回到一行标题', [
    'on'  => '显示',
    'off' => '不显示',
], 'topics');
ecod_admin_field($form, 'select', 'ECatHeadBg', 'cover', '页头背景', '封面取该分类最新一篇文章的首图，取不到时用主题色柔光', [
    'cover' => '最新文章封面',
    'soft'  => '主题色柔光',
], 'topics', $ecodCatHeadWhen);
ecod_admin_field($form, 'select', 'ECatHeadStats', 'on', '页头数据', '篇数 / 累计阅读 / 最近更新，一条聚合查询取全', [
    'on'  => '显示',
    'off' => '不显示',
], 'topics', $ecodCatHeadWhen);
ecod_admin_field($form, 'select', 'ECatHeadTopics', 'on', '相关专题', '分类下文章命中的专题，按命中篇数排序，最多 5 个', [
    'on'  => '显示',
    'off' => '不显示',
], 'topics', $ecodCatHeadWhen);
ecod_admin_field($form, 'select', 'ECatTools', 'on', '分类工具条', '页头下方的工具条：分类内搜索（GET ?q=，/ 热键聚焦）+ 排序按钮（最新 / 最多浏览 / 最多评论 / 随机一篇）；启用时隐藏分类页重复的分类 Tab 条，关闭后回到旧形态', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'topics');

/* ---------------- 搜索（C4 快捷搜索面板） ---------------- */
ecod_admin_field($form, 'select', 'ESearchPanel', 'on', '快捷搜索面板', '⌘K / 点击导航搜索框 / 移动端 dock 搜索按钮唤起居中面板：热词、标签、实时联想与键盘导航；关闭后保留原地的搜索下拉与 ⌘K 聚焦', [
    'on'  => '开启（默认）',
    'off' => '关闭',
], 'search');
ecod_admin_field($form, 'text', 'ESearchHot', '', '热词', '逗号 / 顿号分隔，最多 8 个；留空则面板只显示标签区。热词是编辑推荐，标签是全站概览', [], 'search', ['data-ecod-show-when' => 'ESearchPanel:on', 'placeholder' => 'Typecho, 主题开发, PHP 8.5']);
ecod_admin_field($form, 'select', 'ESearchTagCount', '10', '标签个数', '面板标签区展示的热门标签数量（按引用篇数排序）', [
    '6'  => '6 个',
    '8'  => '8 个',
    '10' => '10 个（默认）',
    '12' => '12 个',
], 'search', ['data-ecod-show-when' => 'ESearchPanel:on']);

/* 隐藏：不进导航的页面 slug（由「工具 → 特色页」的开关写入，逗号分隔） */
ecod_admin_field($form, 'hidden', 'ENavHide', '', '', '', [], 'tools');

/* ------------------------------------------------------------------
 * 传给前端的数据（模块布局数据由模块管理台 widget.php 自己输出）
 * ---------------------------------------------------------------- */
$ecodAdminData = [
    'panes' => array_map(static function ($p) {
        return ['title' => $p[0], 'desc' => $p[1]];
    }, $ecodPanes),
];

/* 选图器：媒体库列表走主题自带接口，上传复用 Typecho 原生动作 */
$ecodUploadIndex = '';
try {
    $ecodUploadIndex = (string) \Widget\Security::alloc()->getIndex('/action/upload');
} catch (\Throwable $e) {
    $ecodUploadIndex = '';
}
?>
<script>
window.ECOD_BLOCKS = <?php echo json_encode($ecodAdminData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
window.ECOD_NAV = <?php echo json_encode([
    'sources' => ecod_nav_sources(),
    'sets'    => ecod_nav_sets()['sets'],
    'active'  => ecod_nav_sets()['active'],
    'home'    => ecod_nav_home_url(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
window.ECOD_TOPICS = <?php echo json_encode([
    'items' => ecod_topics(false),
    'index' => ecod_topic_index(),
    'page'  => ecod_topics_page(),
    'max'   => ECOD_TOPICS_MAX,
    'api'   => ecod_asset('api.php'),
    'token' => ecod_tool_token(),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP); ?>;
window.ECOD_MEDIA = {
    api: <?php echo json_encode(ecod_asset('media.php'), JSON_UNESCAPED_SLASHES); ?>,
    upload: <?php echo json_encode($ecodUploadIndex, JSON_UNESCAPED_SLASHES); ?>
};
window.ECOD_TOOL = {
    api: <?php echo json_encode(ecod_asset('api.php'), JSON_UNESCAPED_SLASHES); ?>,
    token: <?php echo json_encode(ecod_tool_token(), JSON_UNESCAPED_SLASHES); ?>
};
</script>
<script src="<?php echo ecod_asset('assets/admin/media.js'); ?>" defer></script>
<script src="<?php echo ecod_asset('assets/admin/nav.js'); ?>" defer></script>
<script src="<?php echo ecod_asset('assets/admin/topics.js'); ?>" defer></script>
<script src="<?php echo ecod_asset('assets/admin/config.js'); ?>" defer></script>
<script src="<?php echo ecod_asset('assets/admin/tools.js'); ?>" defer></script>
