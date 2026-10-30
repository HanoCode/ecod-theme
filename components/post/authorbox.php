<?php
/**
 * 文章页块 · 文末作者卡
 *
 * 正文结束后的「关于作者」：头像 / 用户组 / 一句话简介 / 文章评论数，
 * 链接到公开作者主页（/author/{uid}/）。JustNews 同款模块，
 * 数据与归档页头卡共用 ecod_user_card()，简介在个人中心维护。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR')) {
    exit;
}

/* 未填简介且只有一个作者的站点往往就是博主本人，版权声明已经覆盖；
   这里只看「有没有卡可渲染」——作者行缺失时静默不输出 */
$ecodBoxUid = 0;
try {
    $ecodBoxUid = (int) $this->author->uid;
} catch (\Throwable $e) {
}
if ($ecodBoxUid <= 0) {
    return;
}
if (!function_exists('ecod_user_card')) {
    require_once __DIR__ . '/../../core/user/meta.php';
}
$ecodBoxCard = ecod_user_card($ecodBoxUid);
if (!$ecodBoxCard) {
    return;
}
?>
<section class="ecod_widget ecod_authorbox-wrap">
    <h2 class="ecod_widget__title"><?php echo htmlspecialchars((string) ecod_p('title', '关于作者'), ENT_QUOTES, 'UTF-8'); ?></h2>
    <?php echo ecod_user_card_html($ecodBoxCard, 'box', ecod_viewer_uid()); ?>
</section>
