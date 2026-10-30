<?php
/**
 * 归档页运行头卡 · 作者主页
 *
 * 把「作者：xxx」的文字头升级成 JustNews 式的公开作者卡：
 * 头像 / 用户组 / 一句话简介 / 文章与评论数 / 加入时间 + TA 的全部文章流。
 * 简介来自个人中心「一句话简介」（ecod_user_meta），作者自己随时可改。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR')) {
    exit;
}

/* authorHandle 把作者 uid 存进 archiveSlug */
$ecodAuthorUid = 0;
try {
    $ecodAuthorUid = (int) $this->getArchiveSlug();
} catch (\Throwable $e) {
}
if (!function_exists('ecod_user_card')) {
    require_once __DIR__ . '/../../core/user/meta.php';
}
$ecodAuthorCard = ecod_user_card($ecodAuthorUid);
if (!$ecodAuthorCard) {
    return;
}

/* 告知 archive.php：头卡已输出 h1，不再补屏幕阅读器标题 */
$GLOBALS['ecodAuthorHeadDone'] = true;

echo ecod_user_card_html($ecodAuthorCard, 'head', ecod_viewer_uid());
