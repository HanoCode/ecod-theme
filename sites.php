<?php
/**
 * 网址导航
 *
 * @package custom
 * @author  Ecod
 * @since   2026-10-30
 * @version 2.0.0
 * @link    https://ecod.cc
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 *
 * 在后台「页面 → 新建页面 → 自定义模板」里选择「网址导航」即可（工具 → 特色页可一键创建）。
 * 站点数据在「设置 → 站点 → 网址导航数据」里维护，格式：
 *   空行分隔分组；每组第一行「组名丨组描述」，之后每行一个站点：
 *   名称丨链接丨一句话描述（第 4 段可选，站点图标地址）
 * 页面正文作为导航页顶部的说明文字。
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}
$this->need('public/header.php');

/* 解析设置里的分组数据（ESites） */
$ecodSitesGroups = [];
$ecodSitesRaw = (string) ecod_option('ESites', '');
if (trim($ecodSitesRaw) !== '') {
    $ecodSitesGroup = null;
    foreach (preg_split('/\r\n|\r|\n/', $ecodSitesRaw) as $ecodSitesLine) {
        $ecodSitesLine = trim($ecodSitesLine);
        if ($ecodSitesLine === '') {
            /* 空行 = 分组结束 */
            $ecodSitesGroup = null;
            continue;
        }
        $ecodSitesParts = array_map('trim', explode('丨', str_replace('|', '丨', $ecodSitesLine)));
        /* 两段且第二段不是链接 → 组头（名称丨描述）；否则按站点行处理 */
        if (count($ecodSitesParts) === 2 && !preg_match('#^https?://#i', $ecodSitesParts[1])) {
            $ecodSitesGroup = ['name' => $ecodSitesParts[0], 'desc' => $ecodSitesParts[1], 'sites' => []];
            $ecodSitesGroups[] = &$ecodSitesGroup;
            unset($ecodSitesGroup);
            continue;
        }
        if ($ecodSitesGroup === null) {
            $ecodSitesGroup = ['name' => '常用站点', 'desc' => '', 'sites' => []];
            $ecodSitesGroups[] = &$ecodSitesGroup;
            unset($ecodSitesGroup);
        }
        if (count($ecodSitesParts) >= 2 && preg_match('#^https?://#i', $ecodSitesParts[1])) {
            $ecodSitesGroup['sites'][] = [
                'name' => mb_substr($ecodSitesParts[0], 0, 30, 'UTF-8'),
                'url'  => $ecodSitesParts[1],
                'desc' => (string) ($ecodSitesParts[2] ?? ''),
                'icon' => (string) ($ecodSitesParts[3] ?? ''),
            ];
        }
    }
    /* 引用传值后统一断开，避免后续循环复用 */
    unset($ecodSitesGroup);
}

/* 页面正文 = 说明文字 */
ob_start();
$this->content();
$ecodSitesNote = trim((string) ob_get_clean());

$ecodSitesCount = 0;
foreach ($ecodSitesGroups as $ecodSitesGroup) {
    $ecodSitesCount += count($ecodSitesGroup['sites']);
}
?>
<main class="ecod_main" id="ecod-main">
    <?php /* 模块 · 内容区上方（模块管理台） */ ecod_widgets_render("top_content", $this); ?>

    <nav class="ecod_crumb" aria-label="面包屑">
        <a class="ecod_crumb__back" href="javascript:history.back()" aria-label="返回"><?php echo ecod_icon('chevron', 13); ?></a>
        <a href="<?php $this->options->siteUrl(); ?>"><?php echo ecod_icon('home', 12); ?>首页</a>
        <span class="ecod_crumb__sep" aria-hidden="true">/</span>
        <span aria-current="page"><?php $this->title(); ?></span>
    </nav>

    <?php ecod_page_hero('导航', (string) $this->title, '常去的工具与站点，一键直达。', '<span>' . ecod_icon('link', 13) . $ecodSitesCount . ' 个站点</span>'); ?>

    <?php if ($ecodSitesNote !== ''): ?>
        <div class="ecod_content ecod_sites__note" data-ecod-content><?php
            echo ecod_go_rewrite(ecod_lazy_images(ecod_keywords_apply(ecod_linkcard_apply(ecod_oembed_apply(ecod_shortcode_apply($ecodSitesNote, $this)))), (string) $this->title));
        ?></div>
    <?php endif; ?>

    <?php if (!$ecodSitesGroups): ?>
        <?php ecod_empty('link', '还没有配置站点', '到「设置 → 站点 → 网址导航数据」里按格式添加'); ?>
    <?php else: ?>
        <?php foreach ($ecodSitesGroups as $ecodSitesGroup): ?>
            <?php if (!$ecodSitesGroup['sites']) { continue; } ?>
            <section class="ecod_sites__group">
                <header class="ecod_section-head<?php echo $ecodSitesGroup['name'] === '' ? ' ecod_section-head--empty' : ''; ?>">
                    <?php if ($ecodSitesGroup['name'] !== ''): ?>
                        <h2 class="ecod_section-head__title"><?php echo ecod_icon('link', 15); ?><?php echo htmlspecialchars($ecodSitesGroup['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
                    <?php endif; ?>
                    <?php if ($ecodSitesGroup['desc'] !== ''): ?>
                        <span class="ecod_section-head__note"><?php echo htmlspecialchars($ecodSitesGroup['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </header>
                <div class="ecod_sites__grid">
                    <?php foreach ($ecodSitesGroup['sites'] as $ecodSitesIndex => $ecodSite): ?>
                        <?php
                        $ecodSiteHost = (string) (parse_url($ecodSite['url'], PHP_URL_HOST) ?: $ecodSite['url']);
                        $ecodSiteTint = ecod_card_tint($ecodSitesIndex + 1);
                        $ecodSiteName = htmlspecialchars($ecodSite['name'], ENT_QUOTES, 'UTF-8');
                        ?>
                        <a class="ecod_site" href="<?php echo htmlspecialchars($ecodSite['url'], ENT_QUOTES, 'UTF-8'); ?>"
                           target="_blank" rel="noopener nofollow" title="<?php echo $ecodSiteName; ?>">
                            <span class="ecod_site__icon" style="background:<?php echo $ecodSiteTint; ?>">
                                <span aria-hidden="true"><?php echo mb_substr($ecodSite['name'], 0, 1, 'UTF-8'); ?></span>
                                <?php if ($ecodSite['icon'] !== '' && preg_match('#^https?://#i', $ecodSite['icon'])): ?>
                                    <img src="<?php echo htmlspecialchars($ecodSite['icon'], ENT_QUOTES, 'UTF-8'); ?>"
                                         alt="" width="40" height="40" loading="lazy" decoding="async" onerror="this.remove()">
                                <?php endif; ?>
                            </span>
                            <span class="ecod_site__main">
                                <strong class="ecod_site__name"><?php echo $ecodSiteName; ?></strong>
                                <?php if ($ecodSite['desc'] !== ''): ?>
                                    <span class="ecod_site__desc"><?php echo htmlspecialchars($ecodSite['desc'], ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                                <span class="ecod_site__host"><?php echo htmlspecialchars($ecodSiteHost, ENT_QUOTES, 'UTF-8'); ?></span>
                            </span>
                            <span class="ecod_site__go" aria-hidden="true"><?php echo ecod_icon('arrow-up-right', 14); ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php /* 模块 · 内容区下方（模块管理台） */ ecod_widgets_render("bottom_content", $this); ?>
</main>

<?php $this->need('public/aside.php'); ?>
<?php $this->need('public/footer.php'); ?>
