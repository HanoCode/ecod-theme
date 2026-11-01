<?php
/**
 * 内置浏览器引导浮层（微信 / QQ / 企业微信 / 钉钉）
 *
 * 结构与交互见 core/site/inapp.php 头注与 docs/inapp-guide.md：
 * 迷你浏览器示意图 + 标题说明 + 地址栏（点击整行或「复制链接」都复制当前页）。
 * 非内置浏览器 UA、或后台关闭时本文件直接 return，不产出任何标记。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

$ecodInApp = ecod_inapp_guide();
if ($ecodInApp === null) {
    return;
}

$ecodInAppSite = trim((string) $this->options->title) ?: '本站';
$ecodInAppMark = mb_substr($ecodInAppSite, 0, 1, 'UTF-8');
$ecodInAppEscape = static function (string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
};
?>
<div class="ecod_inapp" data-ecod-inapp
     data-rate="<?php echo $ecodInAppEscape($ecodInApp['rate']); ?>"
     data-url="<?php echo $ecodInAppEscape($ecodInApp['url']); ?>"
     aria-hidden="true">
    <div class="ecod_inapp__mask" data-ecod-inapp-close></div>
    <div class="ecod_inapp__panel" role="dialog" aria-modal="true" aria-label="改用浏览器打开">
        <button type="button" class="ecod_inapp__x" data-ecod-inapp-close aria-label="关闭提示">
            <?php echo ecod_icon('x', 16); ?>
        </button>

        <?php /* 示意图：CSS 画的迷你浏览器，右上角「···」是真正的操作入口，手指指着它 */ ?>
        <div class="ecod_inapp__demo" aria-hidden="true">
            <div class="ecod_inapp__chrome">
                <span class="ecod_inapp__navs">
                    <?php echo ecod_icon('chevron-left', 12); echo ecod_icon('chevron-right', 12); echo ecod_icon('sync', 11); ?>
                </span>
                <span class="ecod_inapp__pill">
                    <em class="ecod_inapp__fav"><?php echo $ecodInAppEscape($ecodInAppMark); ?></em>
                    <b><?php echo $ecodInAppEscape($ecodInAppSite); ?></b>
                </span>
                <span class="ecod_inapp__dots"><?php echo ecod_icon('chevron-down', 12); ?></span>
            </div>
            <div class="ecod_inapp__view">
                <span class="ecod_inapp__brand"><?php echo $ecodInAppEscape($ecodInAppSite); ?></span>
                <span class="ecod_inapp__row is-1"></span>
                <span class="ecod_inapp__row is-2"></span>
                <span class="ecod_inapp__row is-3"></span>
            </div>
            <span class="ecod_inapp__hand">
                <svg viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.7"
                     stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12.6 18.9V9.4a1.9 1.9 0 0 1 3.8 0v6.2"/>
                    <path d="M16.4 15.6v-1.2a1.8 1.8 0 0 1 3.6 0v2"/>
                    <path d="M20 16.8v-.7a1.8 1.8 0 0 1 3.6 0v4.4c0 3.4-2.3 5.9-5.8 5.9h-1.1c-2 0-3.4-.8-4.5-2.3l-3.1-4.2a1.8 1.8 0 0 1 2.7-2.2l2.1 2.2"/>
                </svg>
            </span>
        </div>

        <h2 class="ecod_inapp__title"><?php echo $ecodInAppEscape($ecodInApp['title']); ?></h2>
        <p class="ecod_inapp__desc"><?php echo $ecodInAppEscape($ecodInApp['desc']); ?></p>
        <p class="ecod_inapp__hint">
            <span class="ecod_inapp__hintic"><?php echo ecod_icon($ecodInApp['icon'], 13); ?></span>
            <span>在<?php echo $ecodInAppEscape($ecodInApp['name']); ?>里：<?php echo $ecodInAppEscape($ecodInApp['menu']); ?></span>
        </p>

        <div class="ecod_inapp__bar" data-ecod-inapp-copy role="button" tabindex="0" aria-label="复制本站链接">
            <span class="ecod_inapp__lock"><?php echo ecod_icon('lock', 12); ?></span>
            <span class="ecod_inapp__host"><?php echo $ecodInAppEscape($ecodInApp['host']); ?></span>
            <span class="ecod_inapp__copy" data-ecod-inapp-copylabel>复制链接</span>
        </div>
    </div>
</div>
