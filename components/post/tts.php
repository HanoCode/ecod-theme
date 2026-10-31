<?php
/**
 * 文章页块 · 阅读工具条（设计稿 §05 C3）
 *
 * 目录（浮目录）/ 朗读（浏览器 SpeechSynthesis）/ 字号 / 阅读进度 一条吸顶横条，
 * 吸收原「朗读条」的横幅形态——不再单独占一条。
 *
 * 朗读控件沿用原 data-ecod-tts 属性契约，ecod.post.js 的 initTts 无需改动即可接管；
 * 浏览器不支持时 initTts 只隐藏本条的朗读钮，工具条其余部分保留。
 *
 * 条件输出（设计稿验收「无目录无朗读时不输出空条」）：
 * - 目录钮：EFloatToc 开启时输出（标题 <2 个时由 JS 隐藏）；
 * - 朗读钮：本模块启用即视为「有朗读」（后台模块开关控制）。
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod. All Rights Reserved.
 * @link    https://ecod.cc
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 密码未解锁不朗读 */
if (!empty($this->hidden)) {
    return;
}

$ecodTtsMinutes = max(1, (int) ceil(ecod_word_count((string) $this->content) / 400));
$ecodTocOn = ecod_option('EFloatToc', 'on') === 'on';
/* 形态（后台「阅读工具条形态」）：吸顶横条 top / 侧栏竖栏 side / 合并到面包屑 crumb；
   side 由 JS 挂 is-side（≥900px 才生效，窄屏自动回落吸顶横条）。
   crumb 形态整条并入面包屑 nav（post.php 设 $ecodReadbarEmbed 后内嵌 include 本文件）；
   组件管理台渲染的实例（未设该标记）不再重复输出 */
$ecodRbMode = ecod_option('EReadBarMode', 'top');
if ($ecodRbMode === 'crumb' && empty($ecodReadbarEmbed)) {
    return;
}
$ecodRbSide = $ecodRbMode === 'side' ? '1' : '0';
?>
<div class="ecod_readbar<?php echo $ecodRbMode === 'crumb' ? ' is-crumb' : ''; ?>" data-ecod-readbar data-ecod-rb-side="<?php echo $ecodRbSide; ?>" aria-label="阅读工具">
    <?php if ($ecodTocOn): ?>
        <button type="button" class="ecod_readbar__tool" data-ecod-readbar-toc aria-haspopup="true">
            <?php echo ecod_icon('list', 15); ?><span>目录</span>
        </button>
    <?php endif; ?>

    <span class="ecod_readbar__tts" data-ecod-tts data-ecod-tts-minutes="<?php echo $ecodTtsMinutes; ?>">
        <button type="button" class="ecod_readbar__tool" data-ecod-tts-toggle aria-pressed="false">
            <span class="ecod_readbar__ic" data-ecod-tts-ic><?php echo ecod_icon('volume', 15); ?></span>
            <span data-ecod-tts-label>朗读</span>
        </button>
        <label class="ecod_readbar__ratebox">
            <select data-ecod-tts-rate aria-label="朗读语速">
                <option value="0.8">0.8×</option>
                <option value="1" selected>1.0×</option>
                <option value="1.25">1.25×</option>
                <option value="1.5">1.5×</option>
            </select>
        </label>
        <button type="button" class="ecod_readbar__stop" data-ecod-tts-stop aria-label="停止朗读" hidden>
            <?php echo ecod_icon('x', 13); ?>
        </button>
    </span>

    <span class="ecod_readbar__fs" data-ecod-rb-fs>
        <button type="button" class="ecod_readbar__tool ecod_readbar__fsbtn" data-ecod-fs="down" aria-label="正文字号调小">A<i>−</i></button>
        <button type="button" class="ecod_readbar__tool ecod_readbar__fsbtn" data-ecod-fs="up" aria-label="正文字号调大">A<i>+</i></button>
    </span>

    <div class="ecod_readbar__prog" aria-hidden="true">
        <span data-ecod-rb-pct>已读 0%</span>
        <span class="ecod_readbar__progl"><i data-ecod-rb-pctbar></i></span>
    </div>
</div>
