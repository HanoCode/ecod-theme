<?php
/**
 * 页脚块 · 实时动态（右下角槽位步进播报，完全对标 guaqi event-ticker）
 *
 * 固定在视口右下（position 参数可镜像到左下），聚合播报站点公开动态
 * （评论 / 积分事件 / 任务奖励 / 新文章 / 新成员），数据来自
 * core/site/activity.php；积分与任务两源沿用「动态播报」隐私开关。
 * 整件 pointer-events:none 不挡正文，但条目保留可点击：<a> 仅自身放开
 * pointer-events（PJAX 跳转对应文章），hover 暂停步进方便点按。
 * 列表单份渲染，卡片绝对定位在 56px 槽位上，
 * 步进动画由 ecod.global.js 的 Ecod.initTicker 接管（可选轮询换血同用此控制器）。
 * 本块在 footer.php 于 #ecod-shell 之外定点输出，跨 PJAX 持久存在。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 多源聚合层（credits / tasks / meta 由其内部按需加载） */
require_once dirname(dirname(__DIR__)) . '/core/site/activity.php';

/* 内容来源：后台 checks 勾选（逗号串），白名单外的由聚合层剔除 */
$ecodTkSources = preg_split('/[\s,，]+/', (string) ecod_p('sources', 'comment,credit,task,post')) ?: [];
$ecodTkSources = array_values(array_filter(array_map('trim', $ecodTkSources)));
if (!$ecodTkSources) {
    ecod_block_setup_hint('内容来源为空：在「页脚 → 实时动态」设置里至少勾选一种「内容来源」（评论 / 积分 / 任务 / 新文章 / 新成员），这里才会滚动播报（访客看不到本提示）。');
    return;
}

$ecodTkNeedCredits = in_array('credit', $ecodTkSources, true) || in_array('task', $ecodTkSources, true);
$ecodTkLimit = max(3, min(15, (int) ecod_p('limit', 8)));
$ecodTkItems = ecod_activity_feed($ecodTkSources, $ecodTkLimit);

/* 空动态：访客零输出；管理员按原因给指路卡（悬浮在原位） */
if (!$ecodTkItems) {
    if ($ecodTkNeedCredits && !ecod_activity_credits_public()) {
        if (!ecod_credits_enabled()) {
            ecod_block_setup_hint('积分系统未开启：先在「设置 → 互动 → 积分与 AI」打开 Credit 积分，积分与任务动态才会播报（评论 / 文章动态不受影响，访客看不到本提示）。');
        } else {
            ecod_block_setup_hint('动态播报未开启：在「设置 → 互动 → 积分与 AI → 动态播报」打开后，积分与任务动态才会展示（评论 / 文章动态不受影响，访客看不到本提示）。');
        }
    } else {
        ecod_block_setup_hint('暂无动态：勾选的内容来源最近没有公开行为，等新动态出现后这里会滚动播报（访客看不到本提示）。');
    }
    return;
}

$ecodTkSeconds = max(10, min(120, (int) ecod_p('seconds', 30)));
$ecodTkVisible = max(2, min(6, (int) ecod_p('visible', 4)));
$ecodTkRefresh = (string) ecod_p('refresh', 'off');
$ecodTkRefresh = in_array($ecodTkRefresh, ['60', '180', '300'], true) ? (int) $ecodTkRefresh : 0;
$ecodTkSide = (string) ecod_p('position', 'right') === 'left' ? 'left' : 'right';

/* 条目渲染：对标 guaqi event-ticker 的卡片视觉，但保留可点击性——
   有 href 出 <a>（PJAX 跳转到对应文章 / 归档），无 href 出 span；
   容器仍 pointer-events:none，仅链接自身放开（见 §37），空隙照旧穿透。
   单份列表由 initTicker 的槽位步进接管滚动，SSR 与轮询重建同构 */
$ecodTkRender = static function (array $ecodTkItem): string {
    $ecodTkHref = (string) $ecodTkItem['href'];
    $ecodTkName = (string) $ecodTkItem['name'];
    $ecodTkText = htmlspecialchars((string) $ecodTkItem['text'], ENT_QUOTES, 'UTF-8');
    if ((int) $ecodTkItem['delta'] !== 0) {
        $ecodTkText .= ' <b class="ecod_ftick__delta">+' . (int) $ecodTkItem['delta'] . '</b>';
    }
    $ecodTkAva = (string) $ecodTkItem['avatar'];
    $ecodTkAvaHtml = $ecodTkAva !== ''
        ? '<img src="' . htmlspecialchars($ecodTkAva, ENT_QUOTES, 'UTF-8') . '" alt="" width="24" height="24" loading="lazy" decoding="async">'
        : '<i>' . htmlspecialchars(mb_substr($ecodTkName, 0, 1, 'UTF-8'), ENT_QUOTES, 'UTF-8') . '</i>';
    $ecodTkInner = '<span class="ecod_ftick__avatar" aria-hidden="true">' . $ecodTkAvaHtml . '</span>'
        . '<span class="ecod_ftick__card">'
        . '<span class="ecod_ftick__meta"><b>' . htmlspecialchars($ecodTkName, ENT_QUOTES, 'UTF-8') . '</b>'
        . '<i aria-hidden="true">·</i>'
        . '<time>' . htmlspecialchars((string) $ecodTkItem['ago'], ENT_QUOTES, 'UTF-8') . '</time></span>'
        . '<span class="ecod_ftick__text">' . $ecodTkText . '</span>'
        . '</span>';
    if ($ecodTkHref === '') {
        return '<span class="ecod_ftick">' . $ecodTkInner . '</span>';
    }
    return '<a class="ecod_ftick" href="' . htmlspecialchars($ecodTkHref, ENT_QUOTES, 'UTF-8') . '">' . $ecodTkInner . '</a>';
};

$ecodTkFirst = $ecodTkItems[0];
?>
<section class="ecod_fticker" data-side="<?php echo $ecodTkSide; ?>"<?php echo $ecodTkRefresh > 0
    ? ' data-ecod-ticker="' . $ecodTkRefresh . '"'
        . ' data-api="' . htmlspecialchars(ecod_api_url(), ENT_QUOTES, 'UTF-8') . '"'
        . ' data-sources="' . htmlspecialchars(implode(',', $ecodTkSources), ENT_QUOTES, 'UTF-8') . '"'
        . ' data-limit="' . $ecodTkLimit . '"'
    : ''; ?>>
    <div class="ecod_fticker__viewport" style="height:<?php echo $ecodTkVisible * 56; ?>px">
        <div class="ecod_fticker__list">
            <?php foreach ($ecodTkItems as $ecodTkItem): ?>
                <?php echo $ecodTkRender($ecodTkItem); ?>
            <?php endforeach; ?>
        </div>
    </div>
    <span class="ecod_fticker__sr"><?php echo htmlspecialchars('最新动态：' . $ecodTkFirst['name'] . ' ' . $ecodTkFirst['text'] . ($ecodTkFirst['delta'] !== 0 ? ' +' . $ecodTkFirst['delta'] : ''), ENT_QUOTES, 'UTF-8'); ?></span>
</section>
