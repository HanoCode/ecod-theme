<?php
/**
 * 文章页块 · 朗读（浏览器 SpeechSynthesis，零依赖）
 *
 * @package Ecod Theme
 * @author  Ecod
 * @copyright Copyright (c) 2026 Ecod (https://ecod.cc)
 * @license   MIT
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

/* 密码未解锁不朗读 */
if (!empty($this->hidden)) {
    return;
}

$ecodTtsWords = ecod_word_count((string) $this->content);
$ecodTtsMinutes = max(1, (int) ceil($ecodTtsWords / 400));
?>
<div class="ecod_tts" data-ecod-tts data-ecod-tts-minutes="<?php echo $ecodTtsMinutes; ?>">
    <button type="button" class="ecod_tts__btn" data-ecod-tts-toggle aria-pressed="false">
        <span class="ecod_tts__ic" data-ecod-tts-ic><?php echo ecod_icon('play', 14); ?></span>
        <span data-ecod-tts-label>朗读本文</span>
    </button>
    <span class="ecod_tts__track" aria-hidden="true"><i data-ecod-tts-bar></i></span>
    <span class="ecod_tts__hint" data-ecod-tts-hint>约 <?php echo $ecodTtsMinutes; ?> 分钟</span>
    <span class="ecod_tts__spacer"></span>
    <label class="ecod_tts__rate">
        <span class="ecod_tts__rate-txt" aria-hidden="true">语速</span>
        <select data-ecod-tts-rate aria-label="朗读语速">
            <option value="0.8">0.8×</option>
            <option value="1" selected>1.0×</option>
            <option value="1.25">1.25×</option>
            <option value="1.5">1.5×</option>
        </select>
    </label>
    <button type="button" class="ecod_tts__stop" data-ecod-tts-stop aria-label="停止朗读" hidden>
        <?php echo ecod_icon('x', 13); ?>
    </button>
</div>
